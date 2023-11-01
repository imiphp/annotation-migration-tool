<?php

declare(strict_types=1);

namespace Imiphp\Tests\Stub {

    use Imi\Bean\Annotation\Bean;
    use Imi\Config\Annotation\ConfigValue;
    use Imi\Server\Http\Message\Contract\IHttpResponse;

    /**
     * @Bean(name="TestClass2", env=@ConfigValue(name="@test", default="cli"))
     */
    #[Bean(name: 'test456', env: 'fpm')]
    class TestClass2
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
         * 正在执行的任务列表.
         *
         * @var \Imi\Cron\CronTask[]
         */
        public array $runningTasks = [];
    }
}
