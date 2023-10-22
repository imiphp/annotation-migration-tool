<?php
declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

use Composer\InstalledVersions;
use Imiphp\Tool\AnnotationMigration\Command\AnnotationMigrationCommand;
use Symfony\Component\Console\Application;

(function () {
    require __DIR__ . '/../../vendor/autoload.php'; // todo 测试
    if (!class_exists(AnnotationMigrationCommand::class))
    {
        (static function () use (&$path) {
            foreach ([
                         $_SERVER['PWD'] ?? null,
                         getcwd(),
                         \dirname(__DIR__, 3),
                         \dirname(__DIR__, 5), // 在非工作路径，使用绝对路径启动
                     ] as $path)
            {
                if (!$path)
                {
                    continue;
                }
                $fileName = $path . '/vendor/autoload.php';
                if (is_file($fileName))
                {
                    require $fileName;

                    \chdir(\dirname($fileName, 2));

                    return;
                }
            }
            echo 'No file vendor/autoload.php', \PHP_EOL;
            exit(255);
        })();
    }

    // todo 测试
    \chdir(\dirname(__DIR__, 2));
    \var_dump(\getcwd());

    $app = new Application('imi phar tool', InstalledVersions::getPrettyVersion('imiphp/annotation-migration') ?: 'dev');
    $command = new AnnotationMigrationCommand();
    $app->add($command);
    $app->setDefaultCommand($command->getName());
    $app->run();
})();
