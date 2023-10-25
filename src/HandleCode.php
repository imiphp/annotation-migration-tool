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

    public function setModified(): void
    {
        $this->modified = true;
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
        if (null === $this->printClosure) {
            return null;
        }

        return ($this->printClosure)();
    }

    public function getCommentRewriteQueue(): array
    {
        return $this->commentRewriteQueue;
    }

    public function pushCommentRewriteQueue(Rewrite\CommentRewriteItem $param, bool $unshift): void
    {
        if ($unshift) {
            \array_unshift($this->commentRewriteQueue, $param);
        } else {
            $this->commentRewriteQueue[] = $param;
        }
    }

    private string $rewriteCodeResult;

    public function rewriteCode(): string
    {
        // 不会支持任何嵌套类

        if (!empty($this->rewriteCodeResult)) {
            return $this->rewriteCodeResult;
        }

        $contents = $this->getContents();

        while ($item = \array_pop($this->commentRewriteQueue)) {

            $commentPadLen = 0;
            $kindNameStartPos = $item->node->getStartFilePos();


            if ($item->newAttribute) {

            }

            if (Node\Stmt\Class_::class === $item->kind && $item->newAttribute) {
                // 忽略处理 class 与行开头之间的可能存在空的情况
                $classKindPos = strrpos(\substr($contents, 0, $kindNameStartPos + 1), 'class');

                if (false !== $classKindPos) {
                    $contents = \substr($contents, 0, $classKindPos)
                        . self::linePadding($item->newAttribute, $commentPadLen). "\n"
                        . \substr($contents, $classKindPos);
                }
            } elseif (Node\Stmt\ClassMethod::class === $item->kind) {

                $testContent = \substr($contents, 0, $kindNameStartPos + 1);
                if (false === \preg_match_all('#\n(\s*)(public|protected|private|function)#i', $testContent, $matches, \PREG_OFFSET_CAPTURE|\PREG_UNMATCHED_AS_NULL)) {
                    continue;
                }
                // 获取行空格对齐
                [$matchAllSet, $commentPadSet] = $matches;
                $targetItem = $matchAllSet[\array_key_last($matchAllSet)];
                $methodLinePos = $targetItem[1] ?? false;

                $commentPadLen = \strlen($commentPadSet[\array_key_last($commentPadSet)][0]);

                if ($item->newAttribute && \is_numeric($methodLinePos)) {
                    $contents = \substr($contents, 0, $methodLinePos + 1)
                        . self::linePadding($item->newAttribute, $commentPadLen) . "\n"
                        . \substr($contents, $methodLinePos + 1);
                }
            } elseif (Node\Stmt\Property::class === $item->kind) {
                $testContent = \substr($contents, 0, $kindNameStartPos + 1);
                if (false === \preg_match_all('#\n(\s*)(public|protected|private)#i', $testContent, $matches, \PREG_OFFSET_CAPTURE|\PREG_UNMATCHED_AS_NULL)) {
                    continue;
                }
                // 获取行空格对齐
                [$matchAllSet, $commentPadSet] = $matches;
                $targetItem = $matchAllSet[\array_key_last($matchAllSet)];
                $methodLinePos = $targetItem[1] ?? false;

                $commentPadLen = \strlen($commentPadSet[\array_key_last($commentPadSet)][0]);

                if ($item->newAttribute && \is_numeric($methodLinePos)) {
                    $contents = \substr($contents, 0, $methodLinePos + 1)
                        . self::linePadding($item->newAttribute, $commentPadLen) . "\n"
                        . \substr($contents, $methodLinePos + 1);
                }
            }

            if ($item->newComment) {
                $contents = \substr($contents, 0, $item->rawDoc->getStartFilePos() )
                    . self::cleanComments($item->newComment, $commentPadLen)
                    . \substr($contents, $item->rawDoc->getEndFilePos() + 1);
            }
        }

        return $this->rewriteCodeResult = $contents;
    }

    protected static function linePadding(string $content, int $len): string
    {
        $output = [];
        $pad = \str_repeat(' ', $len);
        foreach (\explode("\n", $content) as $line) {
            if (\trim($line) === '') {
                continue;
            }
            $output[] = $pad . \ltrim($line);
        }

        return implode("\n", $output);
    }

    public static function cleanComments(string $comments, int $paddingLen = 0): string
    {
        $output = [];
        $pad = \str_repeat(' ', $paddingLen);
        foreach (\explode("\n", $comments) as $comment) {
            $comment1 = \trim($comment);
            if ($comment1 === '') {
                continue;
            }
            if (
                !(preg_match('/^\/\*+$/u', $comment1) === 0
                    && preg_match('/^\*+\/$/u', $comment1) === 0
                    && preg_match('/^\*+$/u', $comment1) === 0
                )
            ) {
                continue;
            }
            if ($comment1[0] === '*') {
                $comment1 = ' ' . $comment1;
            }
            $output[] = $pad . $comment1;
        }

        $output[] = $pad . " */";
        $output = implode("\n", $output);
        if (self::isEmptyComments($output)) {
            return '';
        }
        return "/**\n" . $output;
    }

    protected static function isEmptyComments(string $comments): bool
    {
        foreach (\explode("\n", $comments) as $comment) {
            if (preg_match('/^[\s*\/]*$/', $comment) === 0) {
                return false;
            }
        }
        return true;
    }
}
