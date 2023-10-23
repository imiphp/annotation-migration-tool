<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use Imiphp\Tool\AnnotationMigration\Visitor\RewriteVisitor;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerInterface;
use Yurun\Doctrine\Common\Annotations\AnnotationReader;

class CodeRewriteGenerator
{
    private AnnotationReader $reader;
    private Parser           $parser;
    private Standard         $printer;

    public function __construct(
        readonly public LoggerInterface $logger
    )
    {
        AnnotationReader::addGlobalIgnoredName('noRector');
        $this->reader = new AnnotationReader();

        $factory = new ParserFactory();
        $this->parser = $factory->create(ParserFactory::ONLY_PHP7);

        $this->printer = new Standard([
            'shortArraySyntax' => true,
        ]);
    }

    public function generate(string $filename, bool $rewritePropType): HandleCode
    {
        $handle = new HandleCode(filename: $filename, reader: $this->reader);

        $stmts = $this->parser->parse($handle->getContents());
        $traverser = new NodeTraverser();
        $traverser->addVisitor(
            visitor: new RewriteVisitor(
                handleCode: $handle,
                annotations: [],
                logger: $this->logger,
                rewritePropType: $rewritePropType,
            )
        );
        $modifiedStmts = $traverser->traverse($stmts);

        if ($handle->isModified()) {
            $code = $this->printer->prettyPrintFile($modifiedStmts);

            $handle->setRewriteCode($code);
        }

        return $handle;
    }
}
