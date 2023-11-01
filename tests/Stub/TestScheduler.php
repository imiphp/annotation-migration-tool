<?php

declare(strict_types=1);

namespace Imiphp\Tests\Stub;

use Imi\Aop\Annotation\Inject;
use Imi\Bean\Annotation\Bean;
use Imi\Cron\Contract\ICronManager;

/**
 * @Bean("CronScheduler")
 *
 * 定时任务调度器
 */
class TestScheduler
{
    /**
     * @Inject("CronManager")
     */
    protected ICronManager $cronManager;

    /**
     * 下次执行时间集合.
     */
    protected array $nextTickTimeMap = [];

    /**
     * 正在执行的任务列表.
     *
     * @Inject("CronManager")
     *
     * @var \Imi\Cron\CronTask[]
     */
    protected array $runningTasks = [];

    /**
     * 首次执行记录集合.
     */
    protected array $firstRunMap = [];
}
