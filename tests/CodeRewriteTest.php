<?php
declare(strict_types=1);

namespace Imiphp\Tests;

use Imiphp\Tool\AnnotationMigration\CodeRewriteGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class CodeRewriteTest extends TestCase
{
    public function testRewriteCode(): void
    {
        $logger = new class extends AbstractLogger {
            protected array $logs = [];
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = \sprintf('%s: %s', $level, $message);
            }

            public function getLogs(): array
            {
                return $this->logs;
            }
        };

        $crg = new CodeRewriteGenerator($logger);

        $handle = $crg->generate(__DIR__ . '/Stub/TestClass1.php');

        $this->assertEquals(
            <<<PHP
            <?php
            
            namespace Imiphp\Tests\Stub;
            
            use Imi\Bean\Annotation\Listener;
            use Imi\Util\ImiPriority;
            use Imi\Server\Http\Message\Contract\IHttpResponse;
            use Imi\Server\Http\Route\Annotation\Action;
            use Imi\Bean\Annotation\Bean;
            use Imi\Aop\Annotation\Inject;
            use Imi\Cache\Annotation\Cacheable;
            use Imi\Cron\Contract\ICronManager;
            use Imi\Lock\Annotation\Lockable;
            use Imi\Server\Http\Message\Emitter\SseEmitter;
            use Imi\Server\Http\Message\Emitter\SseMessageEvent;
            
            
            #[Bean(name: 'test456', env: 'fpm')]
            #[Bean(name: 'hotUpdate', env: 'cli')]
            #[Listener(eventName: 'IMI.APP_RUN', priority: 19940312, one: true)]
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
                 * 测试属性123
                 */
                #[Inject(name: 'CronManager')]
                protected ICronManager \$cronManager;
            
                /**
                 * 测试属性123
                 */
                #[Inject(name: 'CronManager2')]
                protected \$cronManager2;
            
                /**
                 * 测试方法
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
            $handle->rewriteCode(),
        );

        $this->assertEmpty($logger->getLogs());
    }
}
