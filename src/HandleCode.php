<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use Imiphp\Tool\AnnotationMigration\Rewrite\CommentRewriteItem;
use PhpParser\Node;
use Yurun\Doctrine\Common\Annotations\Reader;

class HandleCode
{
    protected bool $modified = false;

    private ?\Closure $printClosure = null;
    /**
     * @var array<CommentRewriteItem>
     */
    private array $commentRewriteQueue = [];

    public function __construct(
        readonly public string $filename,
        readonly public Reader $reader,
    ) {
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function setModified(bool $modified = true): void
    {
        $this->modified = $modified;
    }

    public function getContents(): string
    {
        return file_get_contents($this->filename);
    }

    public function setPrintClosure(?\Closure $printClosure): void
    {
        $this->printClosure = $printClosure;
    }

    public function execPrintStmt(): ?string
    {
        if (null === $this->printClosure)
        {
            return null;
        }

        return ($this->printClosure)();
    }

    public function getCommentRewriteQueue(): array
    {
        return $this->commentRewriteQueue;
    }

    public function clearRewriteQueue(): void
    {
        $this->commentRewriteQueue = [];
    }

    public function pushCommentRewriteQueue(Rewrite\CommentRewriteItem $param, bool $unshift): void
    {
        if ($unshift)
        {
            array_unshift($this->commentRewriteQueue, $param);
        }
        else
        {
            $this->commentRewriteQueue[] = $param;
        }
    }

    private string $rewriteCodeResult;

    public function rewriteCode(): string
    {
        // 不会支持任何嵌套类

        if (!empty($this->rewriteCodeResult))
        {
            return $this->rewriteCodeResult;
        }

        $contents = $this->getContents();

        while ($item = array_pop($this->commentRewriteQueue))
        {
            $commentPadLen = 0;
            $kindNameStartPos = $item->node->getStartFilePos();

            if ($item->rootNode instanceof Node\Stmt\ClassLike)
            {
                // 忽略处理 class 与行开头之间的可能存在空的情况
                $testContent = substr($contents, 0, $kindNameStartPos + 1);
                if (false === preg_match_all('#\n(\s*)(final|abstract|trait|class)#i', $testContent, $matches, \PREG_OFFSET_CAPTURE | \PREG_UNMATCHED_AS_NULL))
                {
                    continue;
                }
                // 获取行空格对齐
                [$matchAllSet, $commentPadSet] = $matches;
                $targetItem = $matchAllSet[array_key_last($matchAllSet)];
                $classKindPos = $targetItem[1] ?? false;

                $commentPadLen = \strlen($commentPadSet[array_key_last($commentPadSet)][0]);

                if ($item->newAttribute && is_numeric($classKindPos))
                {
                    $contents = substr($contents, 0, $classKindPos + 1)
                        . self::linePadding($item->newAttribute, $commentPadLen) . "\n"
                        . substr($contents, $classKindPos + 1);
                }
            }
            elseif (Node\Stmt\ClassMethod::class === $item->kind)
            {
                $testContent = substr($contents, 0, $kindNameStartPos + 1);
                if (false === preg_match_all('#\n(\s*)(public|protected|private|function)#i', $testContent, $matches, \PREG_OFFSET_CAPTURE | \PREG_UNMATCHED_AS_NULL))
                {
                    continue;
                }
                // 获取行空格对齐
                [$matchAllSet, $commentPadSet] = $matches;
                $targetItem = $matchAllSet[array_key_last($matchAllSet)];
                $methodLinePos = $targetItem[1] ?? false;

                $commentPadLen = \strlen($commentPadSet[array_key_last($commentPadSet)][0]);

                if ($item->newAttribute && is_numeric($methodLinePos))
                {
                    $contents = substr($contents, 0, $methodLinePos + 1)
                        . self::linePadding($item->newAttribute, $commentPadLen) . "\n"
                        . substr($contents, $methodLinePos + 1);
                }
            }
            elseif (Node\Stmt\Property::class === $item->kind)
            {
                $testContent = substr($contents, 0, $kindNameStartPos + 1);
                if (false === preg_match_all('#\n(\s*)(public|protected|private)#i', $testContent, $matches, \PREG_OFFSET_CAPTURE | \PREG_UNMATCHED_AS_NULL))
                {
                    continue;
                }
                // 获取行空格对齐
                [$matchAllSet, $commentPadSet] = $matches;
                $targetItem = $matchAllSet[array_key_last($matchAllSet)];
                $methodLinePos = $targetItem[1] ?? false;

                $commentPadLen = \strlen($commentPadSet[array_key_last($commentPadSet)][0]);

                if ($item->newAttribute && is_numeric($methodLinePos))
                {
                    $contents = substr($contents, 0, $methodLinePos + 1)
                        . self::linePadding($item->newAttribute, $commentPadLen) . "\n"
                        . substr($contents, $methodLinePos + 1);
                }
            }
            elseif (Node\Stmt\ClassConst::class === $item->kind)
            {
                $testContent = substr($contents, 0, $kindNameStartPos + 1);
                if (false === preg_match_all('#\n(\s*)(public|protected|private|const)#i', $testContent, $matches, \PREG_OFFSET_CAPTURE | \PREG_UNMATCHED_AS_NULL))
                {
                    continue;
                }
                // 获取行空格对齐
                [$matchAllSet, $commentPadSet] = $matches;
                $targetItem = $matchAllSet[array_key_last($matchAllSet)];
                $methodLinePos = $targetItem[1] ?? false;

                $commentPadLen = \strlen($commentPadSet[array_key_last($commentPadSet)][0]);

                if ($item->newAttribute && is_numeric($methodLinePos))
                {
                    $contents = substr($contents, 0, $methodLinePos + 1)
                        . self::linePadding($item->newAttribute, $commentPadLen) . "\n"
                        . substr($contents, $methodLinePos + 1);
                }
            }

            if ($item->newComment && null !== $item->rawDoc)
            {
                $contents = substr($contents, 0, $item->rawDoc->getStartFilePos())
                    . self::cleanComments($item->newComment, $commentPadLen, false)
                    . substr($contents, $item->rawDoc->getEndFilePos() + 1);
            }
        }

        return $this->rewriteCodeResult = $contents;
    }

    protected static function linePadding(string $content, int $len): string
    {
        $output = [];
        $pad = str_repeat(' ', $len);
        foreach (explode("\n", $content) as $line)
        {
            if ('' === trim($line))
            {
                continue;
            }
            $output[] = $pad . ltrim($line);
        }

        return implode("\n", $output);
    }

    public static function cleanComments(string $comments, int $paddingLen = 0, bool $cleanEmptyLine = true): string
    {
        $output = [];
        $pad = str_repeat(' ', $paddingLen);
        foreach (explode("\n", $comments) as $comment)
        {
            if (empty(trim($comment)))
            {
                continue;
            }
            $comment1 = ltrim($comment);
            if (
                !(0 === preg_match('/^\/\*+$/u', $comment1)
                  && 0 === preg_match('/^\*+\/$/u', $comment1)
                )
            ) {
                continue;
            }
            if ('*' === $comment1[0])
            {
                // 往前补充一个空使其对齐
                $comment1 = ' ' . $comment1;
            }
            $output[] = $pad . $comment1;
        }

        $output = self::trimCommentEmpty($output);
        $output = array_reverse($output);
        $output = self::trimCommentEmpty($output);
        $output = array_reverse($output);

        if (self::isEmptyComments($output))
        {
            return '';
        }
        $output[] = $pad . ' */';
        $output = implode("\n", $output);

        return "/**\n" . $output;
    }

    protected static function trimCommentEmpty(array $lines): array
    {
        $output = [];

        $testFirstEmpty = true;
        foreach ($lines as $str)
        {
            if ($testFirstEmpty)
            {
                $str2 = trim($str, " \t\n\r\0\x0B*");
                if (empty($str2))
                {
                    continue;
                }
            }
            $output[] = $str;
            $testFirstEmpty = false;
        }

        return $output;
    }

    protected static function isEmptyComments(array $lines): bool
    {
        foreach ($lines as $comment)
        {
            if (0 === preg_match('/^[\s*\/]*$/', $comment))
            {
                return false;
            }
        }

        return true;
    }
}
