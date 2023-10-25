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

/**
 * @Bean(name="hotUpdate", env="cli")
 * @Listener(eventName="IMI.APP_RUN", priority=ImiPriority::IMI_MAX, one=true)
 */
#[Bean(name: 'test456', env: 'fpm')]
class TestClass1
{
    public const DESCRIPTORSPEC = [
        ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
        ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
    ];

    /**
     * 响应.
     */
    public IHttpResponse $response;

    /**
     * 测试属性123
     *
     * @Inject("CronManager")
     */
    protected ICronManager $cronManager;

    /**
     * 测试属性123
     *
     * @Inject("CronManager2")
     */
    protected $cronManager2;

    /**
     * 测试方法
     *
     * @Cacheable(
     *      key="test:{id}",
     *  )
     *
     * @Cacheable(
     *     key="test:{id}",
     *     ttl=1,
     *     lockable=@Lockable(
     *         id="testCacheableLock:{id}",
     *         waitTimeout=999999,
     *     ),
     *     preventBreakdown=true,
     * )
     */
    public function testCacheableLock(int $id): array
    {
        return [1, 2, 3];
    }

    /**
     * SSE.
     *
     * @Action
     */
    public function sse(): void
    {
        $class = new class() extends SseEmitter {
            protected function task(): void
            {
                echo '123';
            }
        };
    }
}
