<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Visitor;

use Imi\Bean\Annotation\Base as ImiAnnotationBase;
use Imiphp\Tool\AnnotationMigration\BaseRewriteGenerator;
use Imiphp\Tool\AnnotationMigration\HandleCode;
use Imiphp\Tool\AnnotationMigration\Helper;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yurun\Doctrine\Common\Annotations\Reader;

class AttributeRewriteVisitor extends NodeVisitorAbstract
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
        readonly protected BaseRewriteGenerator $generator,
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
            case $node instanceof Node\Stmt\Class_:
                $this->currentClass = $node;
                if ($node->isAnonymous())
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
                if (!$reflection->isSubclassOf(ImiAnnotationBase::class))
                {
                    // 跳过非注解类
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
        if ($this->currentClass instanceof Node\Stmt\Class_ && $this->currentClass?->isAnonymous())
        {
            // 匿名类不处理
            return null;
        }

        return match (true)
        {
            $node instanceof Node\Stmt\ClassMethod => $this->generateClassMethodAttributes(/* @var $node Node\Stmt\ClassMethod */ $node),
            default                                => null,
        };
    }

    private function generateClassMethodAttributes(Node\Stmt\ClassMethod $node): ?Node\Stmt\ClassMethod
    {
        if ('__construct' !== (string) $node->name)
        {
            return null;
        }

        return $this->migrationConstruct($node);
    }

    protected function migrationConstruct(Node\Stmt\ClassMethod $node): Node\Stmt\ClassMethod
    {
        if ($this->debug)
        {
            $this->logger->debug("> Method: {$this->currentClass->name}::{$node->name}");
        }

        $isModified = false;

        $props = $this->currentClass->getProperties();
        $newParams = [];
        foreach ($node->getParams() as $param)
        {
            if ('__data' === $param->var->name)
            {
                $isModified = true;
                continue;
            }
            if (!isset($props[$param->var->name]) && ($param->flags & Node\Stmt\Class_::MODIFIER_PRIVATE) === 0)
            {
                // 提升属性
                $param->flags |= Node\Stmt\Class_::MODIFIER_PUBLIC;

                $isModified = true;
            }
            $newParams[] = $param;
        }

        if (!empty($node->getStmts())) {
            $codeBlock = $this->generator->getPrinter()->prettyPrint($node->getStmts());

            if ('parent::__construct(...\func_get_args());' === $codeBlock)
            {
                // 移除构造方法冗余代码
                $node->stmts = [];
                $isModified = true;
            }
            else
            {
                // 方法存在代码块，请检查
                $this->logger->warning("Method {$this->namespace->name}\\{$this->currentClass->name}::{$node->name} has code block, please check");
            }
        }

        $classCommentDoc = Helper::arrayValueLast($this->currentClass->getComments());
        $classComments = $classCommentDoc?->getText();

        if ($isModified)
        {
            // 重写类属性注解
            $classCommentsLines = $this->parseCommentsProperty($classComments);
            $newClassComments = $this->refactorCommentsProperty($classCommentsLines, $newParams);

            if ($newClassComments !== $classComments)
            {
                $this->currentClass->setDocComment(new Doc($newClassComments));

                // 重写方法注解
                $methodCommentDoc = Helper::arrayValueLast($node->getComments());
                $methodComments = $methodCommentDoc?->getText();
                if ($methodComments)
                {
                    $methodComments = $this->cleanAnnotationFromComments($methodComments, $newParams);
                    if (self::isEmptyComments(explode("\n", $methodComments)))
                    {
                        $node->setDocComment(new Doc(''));
                    }
                    else
                    {
                        $node->setDocComment(new Doc($methodComments));
                    }
                }
            }

            $node->params = $newParams;
            $this->handleCode->setModified();
        }

        return $node;
    }

    /**
     * @return array<array{
     *   index: int,
     *   raw: string,
     *   kind: 'raw'|'property',
     *   meta?: array{
     *     head: string,
     *     type: string,
     *     name: string,
     *     comment: string|null,
     *   },
     * }>
     */
    protected function parseCommentsProperty(string $comments): array
    {
        $comments = explode("\n", $comments);

        $output = [];
        foreach ($comments as $i => $rawLine)
        {
            if (
                !(0 === preg_match('/^\s*\/\*+$/u', $rawLine)
                    && 0 === preg_match('/^\s*\*+\/$/u', $rawLine)
                )
            ) {
                continue;
            }
            if (!preg_match('/^\s*\*\s+@(property|param)/', $rawLine))
            {
                $output[] = ['index' => $i, 'raw' => $rawLine, 'kind' => 'raw'];
                continue;
            }

            if (!preg_match('/^\*\s+@((?:property|param)\S*?)\s+(\S+)\s+\$(\S+)(?:\s([\S\s]+))?/', trim($rawLine), $matchs, \PREG_UNMATCHED_AS_NULL))
            {
                $output[] = ['index' => $i, 'raw' => $rawLine, 'kind' => 'raw'];
                continue;
            }

            $head = trim($matchs[1]);
            $propType = trim($matchs[2]);
            $propName = trim($matchs[3]);
            $propComment = $matchs[4] ? trim($matchs[4]) : null;

            $line = ['index' => $i, 'raw' => $rawLine, 'kind' => $head, 'meta' => [
                'head'    => $head,
                'type'    => $propType,
                'name'    => $propName,
                'comment' => $propComment,
            ]];

            $output[] = $line;
        }

        return $output;
    }

    /**
     * @param array<array{
     *    index: int,
     *    raw: string,
     *    kind: 'raw'|'property',
     *    meta?: array{
     *      head: string,
     *      type: string,
     *      name: string,
     *      comment: string|null,
     *    },
     *  }> $comments
     * @param array<Node\Param> $props
     */
    protected function refactorCommentsProperty(array $comments, array $props): ?string
    {
        $classComments = [];

        $propsMap = [];
        foreach ($props as $prop)
        {
            $propsMap[$prop->var->name] = $prop;
        }

        $commentPropMap = [];
        foreach ($comments as $comment)
        {
            if ('property' !== $comment['kind'])
            {
                $classComments[] = $comment['raw'];
                continue;
            }
            $meta = $comment['meta'];
            $prop = $propsMap[$meta['name']] ?? null;
            if (empty($prop))
            {
                $classComments[] = $comment['raw'];
                continue;
            }
            $commentPropMap[$meta['name']] = $comment;
        }

        foreach ($propsMap as $name => $prop)
        {
            $meta = $commentPropMap[$name]['meta'] ?? null;
            $methodComments = [];
            if (!empty($meta['comment']))
            {
                $methodComments[] = '* ' . $meta['comment'];
            }
            if (empty($prop->type) && isset($meta['type']))
            {
                // 使用文档注解类型
                $methodComments[] = "* @var {$meta['type']}";
            }
            elseif (($prop->type && str_contains($propRawType = $this->generator->getPrinter()->prettyPrint([$prop->type]), 'callable')) || str_contains($meta['type'] ?? '', 'callable'))
            {
                // callable 不能作为属性类型
                $propRawType = empty($propRawType) ? $meta['type'] : $propRawType;
                $methodComments[] = "* @var {$propRawType}";
                $prop->type = null;
                unset($propRawType);
            }
            if (!empty($methodComments))
            {
                $prop->setDocComment(new Doc("/**\n " . implode("\n ", $methodComments) . "\n */"));
            }
        }

        return "/**\n" . implode("\n", $classComments) . "\n*/";
    }

    /**
     * @param array<Node\Param> $props
     */
    protected function cleanAnnotationFromComments(string $comments, array $props): string
    {
        $lines = $this->parseCommentsProperty($comments);

        $propsMap = [];
        foreach ($props as $prop)
        {
            $propsMap[$prop->var->name] = $prop;
        }

        $newComments = [];
        foreach ($lines as $comment)
        {
            if ('param' !== $comment['kind'])
            {
                $newComments[] = $comment['raw'];
                continue;
            }
            $meta = $comment['meta'];
            $prop = $propsMap[$meta['name']] ?? null;
            if (empty($prop))
            {
                $newComments[] = $comment['raw'];
            }
        }

        return "/**\n" . implode("\n", $newComments) . "\n*/";
    }
}
