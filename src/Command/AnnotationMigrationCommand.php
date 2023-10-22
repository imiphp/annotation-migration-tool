<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Command;

use Imiphp\Tool\AnnotationMigration\CodeRewriteGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
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
            ->addOption('dir', 'D', InputOption::VALUE_OPTIONAL, '自定义扫描目录')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, '尝试运行，不生成文件')
            ->setDescription('迁移注解为 PHP8 原生实现');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isDryRun = $input->getOption('dry-run');

        $dirs = [
            'src/Cron',
        ];
        $exclude = [];

        $finder = Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->path('.php')
            ->exclude($exclude)
            ->in($dirs);

        $logger = new ConsoleLogger($output);

        $generator = new CodeRewriteGenerator($logger);

        foreach ($finder as $item) {

            $handle = $generator->generate($item->getRealPath());

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
