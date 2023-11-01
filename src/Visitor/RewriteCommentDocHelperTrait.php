<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Visitor;

use Imi\Bean\Annotation\Base as ImiAnnotationBase;
use Imiphp\Tool\AnnotationMigration\Helper;

trait RewriteCommentDocHelperTrait
{
    protected function removeAnnotationFromCommentsEx(?string $comments, ImiAnnotationBase|string $annotation): ?string
    {
        if (empty($comments))
        {
            return $comments;
        }
        $position = $this->reader->getAnnotationPosition($annotation);
        if (null === $position)
        {
            return $comments;
        }

        $beginPos = $position['headBeginPos'] + $position['beginPos'];
        $stuffLen = $position['length'];

        $beginStr = substr($comments, 0, $beginPos);

        $lineEndPos = strrpos($beginStr, "\n");
        if (false !== $lineEndPos)
        {
            $str1 = substr($beginStr, $lineEndPos + 1);
            if (preg_match('#^\s*\*+\s*$#', $str1))
            {
                $beginStr = substr($beginStr, 0, $lineEndPos + 1);
                $stuffLen += \strlen($str1);
            }
        }

        return $beginStr
            . str_repeat(' ', $stuffLen)
            . substr($comments, $beginPos + $position['length']);
    }

    protected function removeAnnotationFromComments(?string $comments, ImiAnnotationBase|string $annotation): ?string
    {
        if (empty($comments))
        {
            return $comments;
        }
        $reserved = [];
        $exclude = false;
        $class = sprintf('@%s', $this->getClassName($annotation));
        foreach (explode(\PHP_EOL, $comments) as $comment)
        {
            if (false === $exclude && Helper::strStartsWith(ltrim($comment, '\t\n\r\0\x0B* '), $this->compatibleFullyQualifiedClass($class)))
            {
                $exclude = true;
                continue;
            }
            $reserved[] = $comment;
        }
        if (true === $exclude && $this->isEmptyComments($reserved))
        {
            return null;
        }

        return implode(\PHP_EOL, $reserved);
    }

    protected function removeNestedAnnotationFromComments(?string $comments, ImiAnnotationBase|string $annotation): ?string
    {
        if (empty($comments))
        {
            return $comments;
        }
        $class = $this->getClassName($annotation);
        $comments = preg_replace("/{$class}\\(\\{.*\\}\\)/s", '', $comments);

        return $this->isEmptyComments(explode($comments, \PHP_EOL)) ? null : $comments;
    }

    protected function isEmptyComments(array $comments): bool
    {
        foreach ($comments as $comment)
        {
            if (0 === preg_match('/^[\s*\/]*$/', $comment))
            {
                return false;
            }
        }

        return true;
    }
}
