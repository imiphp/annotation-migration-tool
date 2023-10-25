<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Command;

use Imiphp\Tool\AnnotationMigration\CodeRewriteGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Yurun\Doctrine\Common\Annotations\AnnotationReader;

class AnnotationMigrationCommand extends Command
{
    protected static $defaultName = 'annotation';

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, '自定义扫描目录', ['src'])
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, '尝试运行，不生成文件')
            ->setDescription('迁移注解为 PHP8 原生实现');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (\defined('MIGRATION_PROJECT_PARAMS') && !empty(MIGRATION_PROJECT_PARAMS)) {
            $config = MIGRATION_PROJECT_PARAMS;

            if (\is_array($config['globalIgnoredName'] ?? null)) {
                foreach ($config['globalIgnoredName'] as $name) {
                    AnnotationReader::addGlobalIgnoredName($name);
                }
            }
            if (\is_array($config['globalIgnoredNamespace'] ?? null)) {
                foreach ($config['globalIgnoredNamespace'] as $namespace) {
                    AnnotationReader::addGlobalIgnoredNamespace($namespace);
                }
            }
            if (\is_array($config['globalImports'] ?? null)) {
                foreach ($config['globalImports'] as $name => $class) {
                    AnnotationReader::addGlobalImports($name, $class);
                }
            }
        }

        $isDryRun = $input->getOption('dry-run');

        $dirs = $input->getOption('dir');

        $finder = Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->path('.php')
            ->filter(function (\SplFileInfo $file) {
                return !\preg_match('#[\/]?vendor[\/]#iu', $file->getPathname());
            })
            ->in($dirs);

        $logger = new ConsoleLogger($output);

        $generator = new CodeRewriteGenerator($logger, $output->isDebug());

        foreach ($finder as $item) {
            try {
                $handle = $generator->generate(filename: $item->getRealPath());
            } catch (\Throwable $throwable) {
                $output->writeln("Error\t{$item->getRealPath()}");
                $output->writeln('> ' . $throwable);
                $output->writeln('> ' . $throwable->getTraceAsString());
                continue;
            }

            if ($handle->isModified()) {
                $output->writeln("Rewrite\t{$item->getRealPath()}");
                if (!$isDryRun) {
                    file_put_contents($item->getRealPath(), $handle->rewriteCode());
                }
            } else {
                $output->writeln("Skip\t{$item->getRealPath()}");
            }
        }

        return 0;
    }
}
