<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Visitor;

use Imi\Aop\Annotation\Inject;
use Imi\Bean\Annotation\Base as ImiAnnotationBase;
use Imi\Config\Annotation\ConfigValue;
use Imiphp\Tool\AnnotationMigration\HandleCode;
use Imiphp\Tool\AnnotationMigration\Helper;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\BuilderFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yurun\Doctrine\Common\Annotations\Reader;

class RewriteVisitor extends NodeVisitorAbstract
{
    use RewritePropertyHelperTrait;
    use RewriteCommentDocHelperTrait;

    protected Reader $reader;

    protected BuilderFactory    $factory;
    protected ?\ReflectionClass $topClassReflection = null;

    protected ?Node\Stmt\Namespace_ $namespace = null;

    /** @var Node\Stmt\UseUse[] */
    protected array $uses = [];

    protected ?Node\Stmt\Class_ $currentClass = null;

    public function __construct(
        readonly public HandleCode $handleCode,
        readonly public array $annotations = [],
        readonly public LoggerInterface $logger = new NullLogger(),
        readonly protected bool $rewritePropType = true,
    )
    {
        $this->reader = $this->handleCode->reader;
        $this->factory = new BuilderFactory();
    }

    public function enterNode(Node $node)
    {
        switch (true) {
            case $node instanceof Node\Stmt\Namespace_:
                $this->namespace = $node;
                break;
            case $node instanceof Node\Stmt\Class_:
                $this->currentClass = $node;
                if ($node->isAnonymous()) {
                    // 跳过匿名
                    return;
                }
                if ($this->namespace) {
                    $class = $this->namespace->name . '\\' . $node->name;
                } else {
                    $class = $node->name;
                }
                if (!\class_exists($class)) {
                    $this->logger->warning("Class not exists: $class");
                    return;
                }
                $reflection = new \ReflectionClass($class);
                if ($reflection->isSubclassOf(ImiAnnotationBase::class)) {
                    return;
                }
                // 设置顶级类
                $this->topClassReflection = $reflection;
                break;
            case $node instanceof Node\Stmt\Use_:
                foreach ($node->uses as $use) {
                    $this->uses[] = $use;
                }
                break;
        }
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Class_) {
            if (null !== $this->currentClass && \spl_object_id($this->currentClass) !== \spl_object_id($node)) {
                throw new \RuntimeException('Class node not match');
            }
            $this->currentClass = null;
        }
        if (null === $this->topClassReflection) {
            return null;
        }

        return match (true) {
            $node instanceof Node\Stmt\Class_ => $this->generateClassAttributes(/* @var $node Node\Stmt\Class_ */ $node),
            $node instanceof Node\Stmt\ClassMethod => $this->generateClassMethodAttributes(/* @var $node Node\Stmt\ClassMethod */ $node),
            $node instanceof Node\Stmt\Property => $this->generateClassPropertyAttributes(/* @var $node Node\Stmt\Property */ $node),
            default => null,
        };
    }

    private function generateClassAttributes(Node\Stmt\Class_ $node): ?Node\Stmt\Class_
    {
        if ($node->isAnonymous()) {
            // 匿名类不处理
            return null;
        }
        $annotations = $this->reader->getClassAnnotations($this->topClassReflection);
        return $this->generateAttributeAndSaveComments($node, $annotations);
    }

    private function generateClassMethodAttributes(Node\Stmt\ClassMethod $node): ?Node\Stmt\ClassMethod
    {
        if ($this->currentClass?->isAnonymous()) {
            // 匿名类不处理
            return null;
        }
        $method = $this->topClassReflection->getMethod((string) $node->name);
        return $this->generateAttributeAndSaveComments($node, $this->reader->getMethodAnnotations($method));
    }

    private function generateClassPropertyAttributes(Node\Stmt\Property $node): ?Node\Stmt\Property
    {
        if ($this->currentClass?->isAnonymous()) {
            // 匿名类不处理
            return null;
        }
        $property = $this->topClassReflection->getProperty((string) $node->props[0]->name);
        $annotations = $this->reader->getPropertyAnnotations($property);
        $comments = Helper::arrayValueLast($node->getComments())?->getText();
        /** @var ImiAnnotationBase[] $annotations */
        foreach ($annotations as $annotation) {
            if (!$annotation instanceof ImiAnnotationBase) {
                continue;
            }
            if ($this->rewritePropType) {
                /** @var Node\Identifier|Node\Name $type */
                [$type, $fromComment] = $this->guessClassPropertyType($node, $property);
                if ($type) {
                    // todo ?类声明未接口，注入未声明，注解类型为实现类
                    //              // todo 不确定场景
                    //                if (
                    //                    $comment                                                    // type is from comment like @var
                    //                    && ($parentClass = $this->reflection->getParentClass())     // is subclass
                    //                    && $parentClass->hasProperty($property->name)               // parentClass have same property
                    //                    && ! $parentClass->getProperty($property->name)->hasType()   // parentClass property not have type, subclass same on
                    //                ) {
                    //                    if ($annotation instanceof Inject) {
                    //                        if (empty($annotation->name)) {
                    //                            $args = ['value' => $this->getInjectPropertyType($type)];
                    //                        }
                    //                    }
                    //                } else {
                    //                    $node->type = $type;
                    //                }
                    if ($fromComment) {
                        $node->type = $type;
                        $comments = $this->removeAnnotationFromComments($comments, 'var');
                    }
                }
            }
            $node->attrGroups[] = new Node\AttributeGroup([
                new Node\Attribute(
                    $this->guessName($this->getClassName($annotation)),
                    $this->buildAttributeArgs($annotation, $args ?? []),
                ),
            ]);
            $comments = $this->removeAnnotationFromCommentsEx($comments, $annotation);
            $this->handleCode->setModified();
        }
        $node->setDocComment(new Doc((string) $comments));
        return $node;
    }

    /**
     * @param Node\Stmt\Class_ $node
     * @param ImiAnnotationBase[] $annotations
     */
    protected function generateAttributeAndSaveComments(Node $node, array $annotations): Node\Stmt\Class_|Node\Stmt\ClassMethod
    {
        if ($node instanceof Node\Stmt\Class_ && $node->isAnonymous()) {
            // 匿名类不处理
            return $node;
        }
        $comments = Helper::arrayValueLast($node->getComments())?->getText();
        foreach ($annotations as $annotation) {
            if (!$annotation instanceof ImiAnnotationBase) {
                continue;
            }

            if ($this->isNestedAnnotation($annotation)) {
                // todo 完全弃用
                foreach ((array) $annotation as $name => $values) {
                    foreach ($values as $value) {
                        /** @var ImiAnnotationBase $value */
                        $className = $this->getClassName($value);
                        $name = str_contains($className, '\\') ? new Node\Name\FullyQualified($className)
                            : new Node\Name($this->getClassName($value));
                        $node->attrGroups[] = new Node\AttributeGroup([
                            new Node\Attribute(
                                $name,
                                $this->buildAttributeArgs($value),
                            ),
                        ]);
                    }
                    $comments = $this->removeNestedAnnotationFromComments($comments, $annotation);
                }
            } else {
                $className = $this->getClassName($annotation);
                $name = str_contains($className, '\\') ? new Node\Name\FullyQualified($className) : new Node\Name($this->getClassName($annotation));
                $node->attrGroups[] = new Node\AttributeGroup([
                    new Node\Attribute(
                        $name,
                        $this->buildAttributeArgs($annotation),
                    ),
                ]);
                $comments = $this->removeAnnotationFromCommentsEx($comments, $annotation);
            }
            $this->handleCode->setModified();
        }
        $node->setDocComment(new Doc((string) $comments));
        return $node;
    }

    protected function buildAttributeArgs(ImiAnnotationBase $annotation, array $args = []): array
    {
        $args = \array_merge($args, $this->getNotDefaultPropertyFromAnnotation($annotation));
        $args = \array_map(function ($arg) use (&$isNested) {
            if ($arg instanceof ConfigValue) {
                $this->logger->warning("ConfigValue is not supported in attribute, Name: {$arg->name}");
                return $arg->default;
            } elseif (\is_object($arg)) {
                return $this->buildAttributeToNewObject($arg);
            } else {
                return $this->factory->val($arg);
            }
        }, $args);
        return $this->factory->args($args);
    }

    protected function buildAttributeToNewObject(ImiAnnotationBase $annotation)
    {
        $className = $this->getClassName($annotation);
        $name = str_contains($className, '\\') ? new Node\Name\FullyQualified($className) : new Node\Name($this->getClassName($annotation));
        return $this->factory->new($name, $this->buildAttributeArgs($annotation));
    }

    protected function isNestedAnnotation(object $annotation): bool
    {
        return match ($annotation::class) {
//            Middlewares::class => true,
            default => false,
        };
    }

    protected function getClassName($class): string
    {
        $name = is_object($class) ? $class::class : $class;
        foreach ($this->uses as $use) {
            if ($name === $use->name->toString()) {
                if ($use->alias === null) {
                    return Helper::arrayValueLast($use->name->getParts());
                }

                return $use->alias->toString();
            }
        }
        return $name;
    }

    protected function compatibleFullyQualifiedClass(string $class): array
    {
        if (Helper::strStartsWith($class, '\\')) {
            return [$class, substr($class, 1)];
        }
        return [$class, '\\' . $class];
    }

    protected function getNotDefaultPropertyFromAnnotation(ImiAnnotationBase $annotation): array
    {
        $params = $annotation->toArray();
        $ref = new \ReflectionClass($annotation);
        $methodRef = $ref->getConstructor();
        foreach ($methodRef->getParameters() as $parameter) {
            $name = $parameter->getName();
            if ('__data' === $name) {
                continue;
            }
            if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() === $params[$name]) {
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
