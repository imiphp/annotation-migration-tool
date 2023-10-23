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

class AnnotationMigrationCommand extends Command
{
    protected static $defaultName = 'annotation';

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, '自定义扫描目录')
            ->addOption('prop-type', null, InputOption::VALUE_NEGATABLE, '转换属性的注解类型为标准类型声明', true)
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, '尝试运行，不生成文件')
            ->setDescription('迁移注解为 PHP8 原生实现');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $propType = $input->getOption('prop-type');

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

        $generator = new CodeRewriteGenerator($logger);

        foreach ($finder as $item) {
            try {
                $handle = $generator->generate(filename: $item->getRealPath(), rewritePropType: $propType);
            } catch (\Throwable $throwable) {
                $output->writeln("Error\t{$item->getRealPath()}");
                $output->writeln('> ' . $throwable);
                $output->writeln('> ' . $throwable->getTraceAsString());
                continue;
            }

            if ($handle->isModified()) {
                $output->writeln("Rewrite\t{$item->getRealPath()}");
                if (!$isDryRun) {
                    file_put_contents($item->getRealPath(), $handle->getRewriteCode());
                }
            } else {
                $output->writeln("Skip\t{$item->getRealPath()}");
            }
        }

        return 0;
    }
}
