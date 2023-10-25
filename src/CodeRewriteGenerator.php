<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use Imiphp\Tool\AnnotationMigration\Visitor\RewriteVisitor;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerInterface;
use Yurun\Doctrine\Common\Annotations\AnnotationReader;

class CodeRewriteGenerator
{
    protected AnnotationReader $reader;
    protected Parser           $parser;
    protected Standard         $printer;

    public function __construct(
        readonly public LoggerInterface $logger,
        readonly public bool $debug,
    )
    {
        AnnotationReader::addGlobalIgnoredName('noRector');
        $this->reader = new AnnotationReader();

        $factory = new ParserFactory();
        $lexer = new Lexer([
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startTokenPos',
                'endTokenPos',
                'startFilePos',
                'endFilePos',
            ],
        ]);
        $this->parser = $factory->create(ParserFactory::ONLY_PHP7, $lexer);

        $this->printer = new Standard([
            'shortArraySyntax' => true,
        ]);
    }

    public function getPrinter(): Standard
    {
        return $this->printer;
    }

    public function generate(string $filename): HandleCode
    {
        $handle = new HandleCode(filename: $filename, reader: $this->reader);

        $stmts = $this->parser->parse($handle->getContents());
        $traverser = new NodeTraverser();
        $traverser->addVisitor(
            visitor: new RewriteVisitor(
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
