<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Rewrite;

class CommentRewriteItem
{
    public function __construct(
        readonly public string $kind,
        readonly public ?\PhpParser\Node $node,
        readonly public \PhpParser\Comment\Doc $rawDoc,
        readonly public string $newComment,
        readonly public string $newAttribute,
    )
    {
    }
}
