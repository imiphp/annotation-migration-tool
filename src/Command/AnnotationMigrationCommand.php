<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration\Command;

use Imiphp\Tool\AnnotationMigration\AttributeRewriteGenerator;
use Imiphp\Tool\AnnotationMigration\CodeRewriteGenerator;
use Imiphp\Tool\AnnotationMigration\Exception\ErrorAbortException;
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
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '自定义扫描目录', ['src'])
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, '尝试运行，不生成文件')
            ->addOption('annotation-rewrite', 'a', InputOption::VALUE_NONE, '重写注解类')
            ->addOption('catch-continue', null, InputOption::VALUE_NEGATABLE, '遇到异常时继续', true)
            ->addOption('error-continue', null, InputOption::VALUE_NEGATABLE, '遇到错误时继续', true)
            ->addOption('init-config', null, InputOption::VALUE_NONE, '在当前目录生成配置文件')
            ->setDescription('迁移注解为 PHP8 原生实现');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generateConfiguration = $input->getOption('init-config');

        if ($generateConfiguration)
        {
            if (file_exists('./migration-cfg.php'))
            {
                $output->writeln('Configuration file migration-project-params.php already exists');
            }
            copy(__DIR__ . '/../../migration-cfg-example.php', './migration-cfg.php');
            $output->writeln('Generate configuration file migration-cfg.php');

            return 0;
        }

        if (\defined('MIGRATION_PROJECT_PARAMS') && !empty(MIGRATION_PROJECT_PARAMS))
        {
            $config = MIGRATION_PROJECT_PARAMS;

            if (\is_array($config['globalIgnoredName'] ?? null))
            {
                foreach ($config['globalIgnoredName'] as $name)
                {
                    AnnotationReader::addGlobalIgnoredName($name);
                }
            }
            if (\is_array($config['globalIgnoredNamespace'] ?? null))
            {
                foreach ($config['globalIgnoredNamespace'] as $namespace)
                {
                    AnnotationReader::addGlobalIgnoredNamespace($namespace);
                }
            }
            if (\is_array($config['globalImports'] ?? null))
            {
                foreach ($config['globalImports'] as $name => $class)
                {
                    AnnotationReader::addGlobalImports($name, $class);
                }
            }
        }

        $isDryRun = $input->getOption('dry-run');
        $isAnnotationRewrite = $input->getOption('annotation-rewrite');
        $isCatchContinue = $input->getOption('catch-continue');
        $isErrorContinue = $input->getOption('error-continue');

        $dirs = $input->getOption('dir');

        $finder = Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->path('.php')
            ->filter(static fn (\SplFileInfo $file) => !preg_match('#[\/]?vendor[\/]#iu', $file->getPathname()))
            ->in($dirs);

        $logger = new ConsoleLogger($output);

        if ($isAnnotationRewrite)
        {
            $generator = new AttributeRewriteGenerator($logger, $output->isDebug());
        }
        else
        {
            $generator = new CodeRewriteGenerator($logger, $output->isDebug());
        }

        $isError = false;
        foreach ($finder as $item)
        {
            try
            {
                $handle = $generator->generate(filename: $item->getRealPath());
            }
            catch (ErrorAbortException $abortException)
            {
                $isError = true;

                $output->writeln("Error\t{$item->getRealPath()}");
                if ($isErrorContinue)
                {
                    continue;
                }
                else
                {
                    break;
                }
            }
            catch (\Throwable $throwable)
            {
                $isError = true;

                $output->writeln("Error\t{$item->getRealPath()}");
                $output->writeln('> ' . $throwable);
                $output->writeln('> ' . $throwable->getTraceAsString());
                if ($isCatchContinue)
                {
                    continue;
                }
                else
                {
                    break;
                }
            }

            if ($handle->isModified())
            {
                $output->writeln("Rewrite\t{$item->getRealPath()}");
                if (!$isDryRun)
                {
                    if ($isAnnotationRewrite)
                    {
                        $code = $handle->execPrintStmt();
                    }
                    else
                    {
                        $code = $handle->rewriteCode();
                    }
                    file_put_contents($item->getRealPath(), $code);
                }
            }
            else
            {
                $output->writeln("Skip\t{$item->getRealPath()}");
            }
        }

        return $isError ? 1 : 0;
    }
}
