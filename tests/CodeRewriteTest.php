<?php

declare(strict_types=1);

namespace Imiphp\Tests;

use Imiphp\Tool\AnnotationMigration\CodeRewriteGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class CodeRewriteTest extends TestCase
{
    #[DataProvider(methodName: 'rewriteCodeDataProvider')]
    public function testRewriteCode(string $filename, string $expectCode): void
    {
        $logger = new class() extends AbstractLogger {
            protected array $logs = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = sprintf('[%s]: %s', $level, $message);
            }

            public function getLogs(): array
            {
                return $this->logs;
            }
        };

        $crg = new CodeRewriteGenerator($logger, false);

        $handle = $crg->generate($filename);

        foreach ($logger->getLogs() as $log)
        {
            echo $log . \PHP_EOL;
        }

        $this->assertEquals(
            $expectCode,
            $handle->rewriteCode(),
        );
    }

    public static function rewriteCodeDataProvider(): \Generator
    {
        yield 'class1' => [
            __DIR__ . '/Stub/TestClass1.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace Imiphp\Tests\Stub;
            
            use Imi\Aop\Annotation\Inject;
            use Imi\Bean\Annotation\Bean;
            use Imi\Bean\Annotation\Listener;
            use Imi\Cache\Annotation\Cacheable;
            use Imi\Cron\Contract\ICronManager;
            use Imi\Facade\Annotation\Facade;
            use Imi\Lock\Annotation\Lockable;
            use Imi\Server\Http\Message\Contract\IHttpResponse;
            use Imi\Server\Http\Message\Emitter\SseEmitter;
            use Imi\Server\Http\Route\Annotation\Action;
            use Imi\Server\WebSocket\Route\Annotation\WSConfig;
            use Imi\Util\ImiPriority;
            
            
            #[Bean(name: 'test456', env: 'fpm')]
            #[Facade(class: 'Yurun\\\\Swoole\\\\CoPool\\\\ChannelContainer')]
            #[Facade(class: \Imi\Server\WebSocket\Route\Annotation\WSConfig::class)]
            #[Bean(name: 'hotUpdate', env: 'cli')]
            #[Listener(eventName: 'IMI.APP_RUN', priority: 19940312, one: true)]
            #[Listener]
            class TestClass1
            {
                public const DESCRIPTORSPEC = [
                    ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
                    ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
                ];
            
                /**
                 * 响应.
                 */
                public IHttpResponse \$response;
            
                /**
                 * 测试属性123.
                 */
                #[Inject(name: 'CronManager')]
                protected ICronManager \$cronManager;
            
                /**
                 * 测试属性123.
                 */
                #[Inject(name: 'CronManager2')]
                protected \$cronManager2;
            
                /**
                 * 测试方法.
                 */
                #[Cacheable(key: 'test:{id}')]
                #[Cacheable(key: 'test:{id}', ttl: 1, lockable: new Lockable(id: 'testCacheableLock:{id}', waitTimeout: 999999), preventBreakdown: true)]
                public function testCacheableLock(int \$id): array
                {
                    return [1, 2, 3];
                }
            
                /**
                 * SSE.
                 */
                #[Action]
                #[WSConfig(parserClass: \Imi\Server\DataParser\JsonObjectParser::class)]
                public function sse(): void
                {
                    \$class = new class() extends SseEmitter {
                        protected function task(): void
                        {
                            echo '123';
                        }
                    };
                }
            }
            
            PHP,
        ];

        yield 'class2' => [
            __DIR__ . '/Stub/TestClass2.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace Imiphp\Tests\Stub {

                use Imi\Bean\Annotation\Bean;
                use Imi\Config\Annotation\ConfigValue;
                use Imi\Server\Http\Message\Contract\IHttpResponse;
            
                
                #[Bean(name: 'test456', env: 'fpm')]
                #[Bean(name: 'TestClass2', env: 'cli')]
                class TestClass2
                {
                    public const DESCRIPTORSPEC = [
                        ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
                        ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
                    ];
            
                    /**
                     * 响应.
                     */
                    public IHttpResponse \$response;
            
                    /**
                     * 正在执行的任务列表.
                     *
                     * @var \Imi\Cron\CronTask[]
                     */
                    public array \$runningTasks = [];
                }
            }
            
            PHP,
        ];

        yield 'no_namespace' => [
            __DIR__ . '/Stub/no_namespace_class.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);

            /**
             * Smarty Internal Plugin Compile special Smarty Variable Class.
             */
            class no_namespace_class extends \stdClass
            {
            }
            
            PHP,
        ];

        yield 'class_const_and_enum' => [
            __DIR__ . '/Stub/TestEnum.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace Imiphp\Tests\Stub;
            
            use Imi\Enum\Annotation\EnumItem;
            use Imi\Enum\BaseEnum;
            
            class TestEnum extends BaseEnum
            {
                
                #[EnumItem(text: '甲')]
                public const A = 1;

                
                #[EnumItem(text: '乙')]
                protected const B = 2;

                
                #[EnumItem(text: '丙')]
                const C = 3;
            }
            
            PHP,
        ];

        yield 'space1' => [
            __DIR__ . '/Stub/TestScheduler.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace Imiphp\Tests\Stub;
            
            use Imi\Aop\Annotation\Inject;
            use Imi\Bean\Annotation\Bean;
            use Imi\Cron\Contract\ICronManager;
            
            /**
             * 定时任务调度器
             */
            #[Bean(name: 'CronScheduler')]
            class TestScheduler
            {
                
                #[Inject(name: 'CronManager')]
                protected ICronManager \$cronManager;
            
                /**
                 * 下次执行时间集合.
                 */
                protected array \$nextTickTimeMap = [];
            
                /**
                 * 正在执行的任务列表.
                 *
                 *
                 * @var \Imi\Cron\CronTask[]
                 */
                #[Inject(name: 'CronManager')]
                protected array \$runningTasks = [];
            
                /**
                 * 首次执行记录集合.
                 */
                protected array \$firstRunMap = [];
            }
            
            PHP,
        ];

        yield 'annotation_class' => [
            __DIR__ . '/Stub/TestBaseInjectValue.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace Imiphp\Tests\Stub;
            
            use Imi\Bean\Annotation\Base;
            use Imi\Bean\Annotation\Parser;
            
            /**
             * 注入值注解基类.
             */
            #[Parser(className: \Imi\Bean\Parser\BeanParser::class)]
            abstract class TestBaseInjectValue extends Base
            {
                /**
                 * 获取注入值的真实值
                 *
                 * @return mixed
                 */
                abstract public function getRealValue();
            }
            
            PHP,
        ];

        yield 'trait_1' => [
            __DIR__ . '/Stub/TestTPartialClassA1.php',
            <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace Imiphp\Tests\Stub;
            
            use Imi\Bean\Annotation\Partial;
            
            
            #[Partial(class: \Imiphp\Tests\Stub\TestClass1::class)]
            trait TestTPartialClassA1
            {
                public int \$test2Value = 2;
            
                public function test2(): int
                {
                    return \$this->test2Value;
                }
            }
            
            PHP,
        ];
    }
}
