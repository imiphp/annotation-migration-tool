<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerInterface;
use Yurun\Doctrine\Common\Annotations\AnnotationReader;

abstract class BaseRewriteGenerator
{
    protected AnnotationReader $reader;
    protected Parser           $parser;
    protected Standard         $printer;

    public function __construct(
        readonly public LoggerInterface $logger,
        readonly public bool $debug,
    )
    {
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
}
