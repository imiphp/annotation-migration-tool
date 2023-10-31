<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use Imiphp\Tool\AnnotationMigration\Visitor\AttributeRewriteVisitor;
use PhpParser\NodeTraverser;

class AttributeRewriteGenerator extends BaseRewriteGenerator
{
    public function generate(string $filename): HandleCode
    {
        $handle = new HandleCode(filename: $filename, reader: $this->reader);

        $stmts = $this->parser->parse($handle->getContents());
        $traverser = new NodeTraverser();
        $traverser->addVisitor(
            visitor: new AttributeRewriteVisitor(
                generator: $this,
                handleCode: $handle,
                annotations: [],
                logger: $this->logger,
                debug: $this->debug,
            )
        );
        $modifiedStmts = $traverser->traverse($stmts);

        $handle->setPrintClosure(fn () => $this->printer->prettyPrintFile($modifiedStmts));

        return $handle;
    }
}
