<?php

declare(strict_types=1);

namespace Swlib\Event\Helper;

use Swlib\Coroutine\CoroutineContext;
use Swlib\Utils\Log;
use Swoole\Coroutine;
use Throwable;

/**
 * 事件执行器
 * 负责监听器的执行逻辑，包括同步执行、并行执行、结果收集等
 */
class EventExecutor
{
    /**
     * 同步执行单个监听器
     *
     * @param array|callable $listener 监听器
     * @param array $args 参数
     * @return mixed 执行结果
     */
    public static function run(array|callable $listener, array $args): mixed
    {
        try {
            if (is_callable($listener)) {
                return call_user_func_array($listener, $args);
            } elseif (is_array($listener)) {
                if (isset($listener[0]) && isset($listener[1])) {
                    [$className, $method] = $listener;
                    return new $className()->$method($args);
                }
            }
        } catch (Throwable $e) {
            Log::saveException($e, 'event');
        }
        return null;
    }

    /**
     * 同步顺序执行所有监听器
     *
     * @param array $listeners 监听器列表（已排序）
     * @param array $args 参数
     * @param bool $collectResult 是否收集结果
     * @return EventResponse|null 执行结果，当 collectResult 为 false 时返回 null
     */
    public static function runSync(array $listeners, array $args, bool $collectResult = false): ?EventResponse
    {
        if ($collectResult === false) {
            foreach ($listeners as $item) {
                $result = self::run($item['listener'], $args);
                if ($result === false) {
                    break;
                }
            }
            return null;
        }

        $results = [];
        $propagate = true;
        $listenerCount = count($listeners);
        $executionStartTime = microtime(true);

        foreach ($listeners as $index => $item) {
            if (!$propagate) break;

            $listenerStartTime = microtime(true);
            $result = self::run($item['listener'], $args);
            $listenerEndTime = microtime(true);

            $results[] = new ListenerResult(
                listenerIndex: $index,
                listenerInfo: self::getListenerInfo($item['listener']),
                priority: $item['priority'],
                result: $result,
                executedAt: $listenerStartTime,
                executionTime: round($listenerEndTime - $listenerStartTime, 6)
            );

            if ($result === false) {
                $propagate = false;
            }
        }

        $executionEndTime = microtime(true);

        return new EventResponse(
            totalListeners: $listenerCount,
            executedListeners: count($results),
            stoppedEarly: !$propagate,
            executionStartTime: $executionStartTime,
            executionEndTime: $executionEndTime,
            executionDuration: round($executionEndTime - $executionStartTime, 6),
            results: $results
        );
    }

    /**
     * 并行执行所有监听器（异步模式）
     * 使用协程并发执行，不阻塞当前流程
     *
     * @param array $listeners 监听器列表
     * @param array $args 参数
     * @param array $parentContext 父协程上下文
     * @return void
     */
    public static function runAsync(array $listeners, array $args, array $parentContext): void
    {
        foreach ($listeners as $item) {
            Coroutine::create(function () use ($item, $args, $parentContext) {
                // 恢复协程上下文
                CoroutineContext::restore($parentContext);
                self::run($item['listener'], $args);
            });
        }
    }

    /**
     * 获取监听器信息，用于调试和日志
     *
     * @param array|callable $listener
     * @return string
     */
    public static function getListenerInfo(array|callable $listener): string
    {
        if (is_array($listener) && count($listener) === 2) {
            return $listener[0] . '::' . $listener[1];
        } elseif (is_callable($listener)) {
            if (is_object($listener)) {
                return 'Closure';
            } elseif (is_string($listener)) {
                return $listener;
            } else {
                return 'Callable';
            }
        }
        return 'Unknown';
    }
}

