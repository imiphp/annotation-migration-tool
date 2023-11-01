<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Visitor;

use Imi\Bean\Annotation\Base as ImiAnnotationBase;
use Imi\Config\Annotation\ConfigValue;
use Imiphp\Tool\AnnotationMigration\CodeRewriteGenerator;
use Imiphp\Tool\AnnotationMigration\Exception\ErrorAbortException;
use Imiphp\Tool\AnnotationMigration\HandleCode;
use Imiphp\Tool\AnnotationMigration\Helper;
use Imiphp\Tool\AnnotationMigration\Rewrite\CommentRewriteItem;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yurun\Doctrine\Common\Annotations\Reader;

class RewriteVisitor extends NodeVisitorAbstract
{
    use RewriteCommentDocHelperTrait;
    use RewritePropertyHelperTrait;

    protected Reader $reader;

    protected BuilderFactory $factory;
    protected ?\ReflectionClass $topClassReflection = null;

    protected ?Node\Stmt\Namespace_ $namespace = null;

    /** @var Node\Stmt\UseUse[] */
    protected array $uses = [];

    protected ?Node\Stmt\ClassLike $currentClass = null;

    protected bool $isAbort = false;

    public function __construct(
        readonly protected CodeRewriteGenerator $generator,
        readonly public HandleCode $handleCode,
        readonly public array $annotations = [],
        readonly public LoggerInterface $logger = new NullLogger(),
        readonly public bool $debug = false,
    ) {
        $this->reader = $this->handleCode->reader;
        $this->factory = new BuilderFactory();
    }

    protected function abort(): void
    {
        $this->isAbort = true;
        // 目前方案保留已经成功处理的更改
        // $this->handleCode->setModified(false);
        // $this->handleCode->clearRewriteQueue();
    }

    protected function clearModified(): void
    {
        $this->handleCode->setModified(false);
        $this->handleCode->clearRewriteQueue();
    }

    public function enterNode(Node $node): void
    {
        if ($this->isAbort)
        {
            return;
        }

        switch (true)
        {
            case $node instanceof Node\Stmt\Namespace_:
                if (null !== $this->namespace && $node->name !== $this->namespace->name)
                {
                    // 不支持多个类声明
                    $this->logger->warning('Multiple namespace not supported');
                    $this->abort();

                    return;
                }
                $this->namespace = $node;
                if ($this->debug)
                {
                    $this->logger->debug("> Enter block namespace: {$node?->name}");
                }
                break;
            case $node instanceof Node\Stmt\ClassLike:
                $this->currentClass = $node;
                if ($node instanceof Node\Stmt\Class_ && $node->isAnonymous())
                {
                    // 跳过匿名
                    return;
                }
                elseif (null !== $this->topClassReflection)
                {
                    // 不支持多个类声明
                    $this->logger->warning('Multiple class not supported');
                    $this->abort();

                    return;
                }
                if ($this->namespace)
                {
                    $class = $this->namespace->name . '\\' . $node->name;
                }
                else
                {
                    $class = (string) $node->name;
                }
                if ($this->debug)
                {
                    $this->logger->debug("> Enter block class: {$class}");
                }
                try
                {
                    $reflection = new \ReflectionClass($class);
                }
                catch (\ReflectionException $e)
                {
                    $this->logger->warning("Class not exists: {$class}");

                    return;
                }
                // 设置顶级类
                $this->topClassReflection = $reflection;
                break;
            case $node instanceof Node\Stmt\Use_:
                foreach ($node->uses as $use)
                {
                    $this->uses[] = $use;
                }
                break;
        }
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($this->isAbort)
        {
            return null;
        }
        if ($node instanceof Node\Stmt\ClassLike)
        {
            if (null !== $this->currentClass && spl_object_id($this->currentClass) !== spl_object_id($node))
            {
                throw new \RuntimeException('Class node not match');
            }
            $this->currentClass = null;
        }
        if (null === $this->topClassReflection)
        {
            return null;
        }

        return match (true)
        {
            $node instanceof Node\Stmt\ClassLike => $this->generateClassAttributes(/* @var $node Node\Stmt\ClassLike */ $node),
            // $node instanceof Node\Stmt\Trait_ => $this->generateClassAttributes(/* @var $node Node\Stmt\Trait_ */ $node),
            $node instanceof Node\Stmt\ClassMethod => $this->generateClassMethodAttributes(/* @var $node Node\Stmt\ClassMethod */ $node),
            $node instanceof Node\Stmt\Property    => $this->generateClassPropertyAttributes(/* @var $node Node\Stmt\Property */ $node),
            $node instanceof Node\Stmt\ClassConst  => $this->generateClassConstAttributes(/* @var $node Node\Stmt\ClassConst */ $node),
            default                                => null,
        };
    }

    private function generateClassAttributes(Node\Stmt\ClassLike $node): null|Node\Stmt\ClassLike
    {
        if ($node instanceof Node\Stmt\Class_ && $node->isAnonymous())
        {
            // 匿名类不处理
            return null;
        }
        $annotations = $this->reader->getClassAnnotations($this->topClassReflection);

        return $this->generateAttributeAndSaveComments($node, $annotations);
    }

    private function generateClassMethodAttributes(Node\Stmt\ClassMethod $node): ?Node\Stmt\ClassMethod
    {
        if ($this->currentClass instanceof Node\Stmt\Class_ && $this->currentClass?->isAnonymous())
        {
            // 匿名类不处理
            return null;
        }
        $method = $this->topClassReflection->getMethod((string) $node->name);

        return $this->generateAttributeAndSaveComments($node, $this->reader->getMethodAnnotations($method));
    }

    private function generateClassPropertyAttributes(Node\Stmt\Property $node): ?Node\Stmt\Property
    {
        if ($this->currentClass instanceof Node\Stmt\Class_ && $this->currentClass?->isAnonymous())
        {
            // 匿名类不处理
            return null;
        }
        $propName = (string) $node->props[0]->name;
        if ($this->debug)
        {
            $this->logger->debug("> Property: {$this->currentClass->name}::{$propName}");
        }
        $property = $this->topClassReflection->getProperty($propName);
        $annotations = $this->reader->getPropertyAnnotations($property);
        $attrGroups = [];
        $commentDoc = Helper::arrayValueLast($node->getComments());
        $comments = $commentDoc?->getText();
        /** @var ImiAnnotationBase[] $annotations */
        foreach ($annotations as $annotation)
        {
            if (!$annotation instanceof ImiAnnotationBase)
            {
                continue;
            }
            $attrGroups[] = new Node\AttributeGroup([
                new Node\Attribute(
                    $this->guessName($this->getClassName($annotation)),
                    $this->buildAttributeArgs($annotation, $args ?? []),
                ),
            ]);
            $comments = $this->removeAnnotationFromCommentsEx($comments, $annotation);
            $this->handleCode->setModified();
        }

        if ($this->handleCode->isModified())
        {
            $attribute = $attrGroups ? $this->generator->getPrinter()->prettyPrint($attrGroups) : '';
            $this->handleCode->pushCommentRewriteQueue(new CommentRewriteItem(
                kind: $node::class,
                rootNode: $node,
                node: $node->props[0],
                rawDoc: $commentDoc,
                newComment: (string) $comments,
                newAttribute: $attribute,
            ), false);
        }

        return $node;
    }

    private function generateClassConstAttributes(Node\Stmt\ClassConst $node): ?Node\Stmt\ClassConst
    {
        if ($this->currentClass instanceof Node\Stmt\Class_ && $this->currentClass?->isAnonymous())
        {
            // 匿名类不处理
            return null;
        }

        $constName = (string) $node->consts[0]->name;
        if ($this->debug)
        {
            $this->logger->debug("> Const: {$this->currentClass->name}::{$constName}");
        }
        $constant = $this->topClassReflection->getReflectionConstant($constName);
        $annotations = $this->reader->getConstantAnnotations($constant);
        $attrGroups = [];
        $commentDoc = Helper::arrayValueLast($node->getComments());
        $comments = $commentDoc?->getText();
        /** @var ImiAnnotationBase[] $annotations */
        foreach ($annotations as $annotation)
        {
            if (!$annotation instanceof ImiAnnotationBase)
            {
                continue;
            }
            $attrGroups[] = new Node\AttributeGroup([
                new Node\Attribute(
                    $this->guessName($this->getClassName($annotation)),
                    $this->buildAttributeArgs($annotation, $args ?? []),
                ),
            ]);
            $comments = $this->removeAnnotationFromCommentsEx($comments, $annotation);
            $this->handleCode->setModified();
        }

        if ($this->handleCode->isModified())
        {
            $attribute = $attrGroups ? $this->generator->getPrinter()->prettyPrint($attrGroups) : '';
            $this->handleCode->pushCommentRewriteQueue(
                new CommentRewriteItem(
                    kind: $node::class,
                    rootNode: $node,
                    node: $node->consts[0],
                    rawDoc: $commentDoc,
                    newComment: (string) $comments,
                    newAttribute: $attribute,
                ),
                false,
            );
        }

        return $node;
    }

    /**
     * @param Node\Stmt\ClassLike $node
     * @param ImiAnnotationBase[] $annotations
     */
    protected function generateAttributeAndSaveComments(Node $node, array $annotations): Node\Stmt\ClassLike|Node\Stmt\ClassMethod
    {
        if ($node instanceof Node\Stmt\Class_ && $node->isAnonymous())
        {
            // 匿名类不处理
            return $node;
        }
        if ($node instanceof Node\Stmt\ClassMethod && $node->isMagic())
        {
            // 魔术方法不处理
            return $node;
        }
        if ($this->debug)
        {
            if ($node instanceof Node\Stmt\ClassMethod)
            {
                $this->logger->debug("> Method: {$this->currentClass->name}::{$node->name}");
            }
            else
            {
                $this->logger->debug("> Class: {$node->name}");
            }
        }
        $attrGroups = [];
        $commentDoc = Helper::arrayValueLast($node->getComments());
        $comments = $commentDoc?->getText();
        foreach ($annotations as $annotation)
        {
            if (!$annotation instanceof ImiAnnotationBase)
            {
                continue;
            }

            $className = $this->getClassName($annotation);
            $name = str_contains($className, '\\') ? new Node\Name\FullyQualified($className) : new Node\Name($this->getClassName($annotation));
            $attrGroups[] = new Node\AttributeGroup([
                new Node\Attribute(
                    $name,
                    $this->buildAttributeArgs($annotation),
                ),
            ]);
            $comments = $this->removeAnnotationFromCommentsEx($comments, $annotation);

            $this->handleCode->setModified();
        }

        if ($this->handleCode->isModified())
        {
            if (empty($attrGroups) && empty($commentDoc))
            {
                return $node;
            }
            $attribute = $attrGroups ? $this->generator->getPrinter()->prettyPrint($attrGroups) : '';
            $this->handleCode->pushCommentRewriteQueue(new CommentRewriteItem(
                kind: $node::class,
                rootNode: $node,
                node: $node->name,
                rawDoc: $commentDoc,
                newComment: (string) $comments,
                newAttribute: $attribute,
            ), $node instanceof Node\Stmt\ClassLike);
        }

        return $node;
    }

    protected function buildAttributeArgs(ImiAnnotationBase $annotation, array $args = []): array
    {
        $paramNames = [];
        $args = array_merge($args, $this->getNotDefaultPropertyFromAnnotation($annotation, $paramNames));
        $newArgs = [];
        foreach ($args as $key => $arg)
        {
            if (!\in_array($key, $paramNames, true))
            {
                $this->abort();
                $this->clearModified();
                $message = sprintf('Attribute %s has extra argument: %s', $annotation::class, $key);
                $this->logger->error($message);
                throw new ErrorAbortException($message);
            }
            if ($arg instanceof ConfigValue)
            {
                $this->logger->warning("ConfigValue is not supported in attribute, Name: {$arg->name}");
                $newArgs[$key] = $arg->default;
            }
            elseif (\is_object($arg))
            {
                $newArgs[$key] = $this->buildAttributeToNewObject($arg);
            }
            elseif (
                str_contains(strtolower($key), 'class')
                && \is_string($arg)
                && preg_match('/^\S+$/', $arg) > 0
                && class_exists($arg)
            ) {
                $newArgs[$key] = $this->factory->classConstFetch(
                    $this->guessName($arg),
                    new Node\Identifier('class')
                );
            }
            else
            {
                $newArgs[$key] = $this->factory->val($arg);
            }
        }

        return $this->factory->args($newArgs);
    }

    protected function buildAttributeToNewObject(ImiAnnotationBase $annotation)
    {
        $className = $this->getClassName($annotation);
        $name = str_contains($className, '\\') ? new Node\Name\FullyQualified($className) : new Node\Name($this->getClassName($annotation));

        return $this->factory->new($name, $this->buildAttributeArgs($annotation));
    }

    protected function getClassName($class): string
    {
        $name = \is_object($class) ? $class::class : $class;
        foreach ($this->uses as $use)
        {
            if ($name === $use->name->toString())
            {
                if (null === $use->alias)
                {
                    return Helper::arrayValueLast($use->name->getParts());
                }

                return $use->alias->toString();
            }
        }

        return $name;
    }

    protected function compatibleFullyQualifiedClass(string $class): array
    {
        if (Helper::strStartsWith($class, '\\'))
        {
            return [$class, substr($class, 1)];
        }

        return [$class, '\\' . $class];
    }

    protected function getNotDefaultPropertyFromAnnotation(ImiAnnotationBase $annotation, array &$paramNames = []): array
    {
        $params = $annotation->toArray();
        $ref = new \ReflectionClass($annotation);
        $methodRef = $ref->getConstructor();
        $paramNames = [];
        foreach ($methodRef->getParameters() as $parameter)
        {
            $name = $parameter->getName();
            if ('__data' === $name)
            {
                continue;
            }
            $paramNames[] = $name;
            if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() === $params[$name])
            {
                unset($params[$name]);
            }
        }

        return $params;
    }

    protected function baseType(): array
    {
        return [
            'bool',
            'int',
            'string',
            'object',
            'array',
        ];
    }
}
