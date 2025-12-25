<?php

namespace Swlib\Crontab;

use DateTime;
use Generate\CrontabMap;
use Swlib\Utils\ConsoleColor;
use Swoole\Coroutine;
use Swoole\Coroutine\System;
use Swlib\Utils\Log;
use Swoole\Process;
use Swoole\Timer;
use Throwable;

/**
 * Crontab 调度器
 *
 * 单进程多协程实现，管理所有定时任务的执行
 */
class CrontabScheduler
{
    private array $lastExecuted = [];


    /**
     * 启动调度器
     */
    public static function run($server): void
    {
        if (empty(CrontabMap::TASKS)) {
            return;
        }
        $crontabProcess = new Process(
            callback: function () use ($server) {
                try {
                    $scheduler = new CrontabScheduler();
                    $scheduler->start($server);
                } catch (Throwable $e) {
                    fwrite(STDERR, "[crontab] " . $e->getMessage() . PHP_EOL);
                    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
                    exit(1);
                }
            },
            enable_coroutine: true
        );
        $crontabProcess->start();


    }

    /**
     * 启动主循环
     */
    private function start($server): void
    {
        ConsoleColor::write("CrontabScheduler started");
        while (true) {
            try {
                $now = new DateTime();
                $this->checkAndExecuteTasks($server, $now);

                // 每秒检查一次
                System::sleep(1);
            } catch (Throwable $e) {
                Log::error("CrontabScheduler error: " . $e->getMessage(), [], 'crontab');
            }
        }
    }

    /**
     * 检查并执行到期的任务
     */
    private function checkAndExecuteTasks($server, DateTime $now): void
    {
        foreach (CrontabMap::TASKS as $task) {
            try {
                $taskId = $task['run'][0] . '::' . $task['run'][1];

                // 防止重复执行（同一分钟内只执行一次）
                $currentMinute = $now->format('Y-m-d H:i');
                if (isset($this->lastExecuted[$taskId]) && $this->lastExecuted[$taskId] === $currentMinute) {
                    continue;
                }

                // 判断是否应该执行
                $cronExpression = new CronExpression($task['cron']);
                if (!$cronExpression->isDue($now)) {
                    continue;
                }

                // 记录执行时间
                $this->lastExecuted[$taskId] = $currentMinute;

                // 创建协程执行任务
                if ($task['enable_coroutine']) {
                    Coroutine::create(function () use ($task, $server, $taskId) {
                        $this->executeTask($task, $server, $taskId);
                    });
                } else {
                    $this->executeTask($task, $server, $taskId);
                }
            } catch (Throwable $e) {
                Log::error("Error checking task: " . $e->getMessage(), [], 'crontab');
            }
        }
    }

    /**
     * 执行单个任务
     */
    private function executeTask(array $task, $server, string $taskId): void
    {
        $startTime = microtime(true);
        $className = $task['run'][0];
        $methodName = $task['run'][1];
        $timeout = $task['timeout'] ?? 300;

        try {
            Log::info("Task started: $taskId", [], 'crontab');

            // 实例化任务类
            $instance = new $className();

            // 设置超时
            if ($timeout > 0 && Coroutine::getCid() > 0) {
                $timerId = null;
                $timerId = Timer::after($timeout * 1000, function () use ($taskId, $timerId, $timeout) {
                    if ($timerId !== null) {
                        Log::error("Task timeout: $taskId (timeout: {$timeout}s)", [], 'crontab');
                    }
                });

                try {
                    $instance->$methodName($server);
                } finally {
                    Timer::clear($timerId);
                }
            } else {
                // 非协程环境或无超时限制
                $instance->$methodName($server);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Task completed: $taskId ({$duration}ms)", [], 'crontab');
        } catch (Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::error("Task failed: $taskId ({$duration}ms) - " . $e->getMessage(), [], 'crontab');
        }
    }
}

