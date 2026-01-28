<?php

namespace Swlib\Event\Trait;

use Exception;
use Generate\EventMap;
use Swlib\Coroutine\CoroutineContext;
use Swlib\DataManager\ReflectionManager;
use Swlib\Event\Attribute\Event;
use Swlib\Event\Helper\EventExecutor;
use Swlib\Event\Helper\EventQueue;
use Swlib\Event\Helper\EventResponse;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Utils\Log;
use Swoole\Coroutine;
use Swoole\Timer;

trait EventTrait
{
    private static array $listeners = [];

    /**
     * 注册事件监听器
     *
     * @param string $event 事件名称
     * @param array|callable $listener [className, methodName] 或 callable
     * @param int $priority 优先级，数字越小优先级越高，默认为10
     * @throws Exception
     */
    public static function on(string $event, array|callable $listener, int $priority = 10): void
    {
        if (is_array($listener)) {
            if (count($listener) !== 2) {
                throw new AppException(AppErr::EVENT_LISTENER_FORMAT_NEED_ARRAY);
            }
            [$className, $method] = $listener;

            if (!class_exists($className)) {
                throw new AppException(AppErr::EVENT_LISTENER_CLASS_NOT_EXIST_WITH_NAME . ": $className");
            }

            $reflection = ReflectionManager::getClass($className);
            if (!$reflection->hasMethod($method)) {
                throw new AppException(AppErr::EVENT_LISTENER_METHOD_NOT_EXIST_IN_CLASS . ": 方法 $method 不存在于类 $className 中");
            }
        }

        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority
        ];
    }

    /**
     * 移除事件监听器
     *
     * @param string $event 事件名称
     * @param callable|array $listener 监听器
     */
    public static function off(string $event, callable|array $listener): void
    {
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $index => $item) {
                if (self::isSameListener($listener, $item['listener'])) {
                    unset(self::$listeners[$event][$index]);
                }
            }
            if (empty(self::$listeners[$event])) {
                unset(self::$listeners[$event]);
            }
        }
    }

    /**
     * 触发事件（统一入口）
     *
     * 支持通过参数自由组合实现不同的执行模式：
     *
     * 参数说明：
     * - async: 是否异步（不阻塞当前流程），监听器并行执行
     * - queue: 是否使用队列（保证事件处理顺序），监听器串行执行
     * - delay: 延迟时间（毫秒），延迟后再执行
     *
     * 组合示例：
     * ```php
     * // 1. 同步执行（默认）- 阻塞，返回结果
     * $result = Event::emit('user.login', $args);
     *
     * // 2. 异步执行 - 不阻塞，监听器并行执行
     * Event::emit('user.login', $args, async: true);
     *
     * // 3. 队列执行 - 不阻塞，监听器按队列顺序串行执行
     * Event::emit('user.login', $args, queue: true);
     *
     * // 4. 延迟执行 - 延迟后同步执行
     * Event::emit('user.login', $args, delay: 1000);
     *
     * // 5. 延迟 + 异步 - 延迟后监听器并行执行
     * Event::emit('user.login', $args, delay: 1000, async: true);
     *
     * // 6. 延迟 + 队列 - 延迟后放入队列串行执行
     * Event::emit('user.login', $args, delay: 1000, queue: true);
     *
     * // 7. 异步 + 队列 - 不阻塞，放入队列（queue优先）
     * Event::emit('user.login', $args, async: true, queue: true);
     *
     * // 8. 延迟 + 异步 + 队列 - 延迟后放入队列
     * Event::emit('user.login', $args, delay: 1000, async: true, queue: true);
     * ```
     *
     * @param string $event 事件名称
     * @param array|object $args 参数
     * @param bool $async 是否异步执行（不阻塞，监听器并行）
     * @param int $delay 延迟时间（毫秒），0表示不延迟
     * @param bool $queue 是否使用队列（不阻塞，监听器串行）
     * @return int|EventResponse|false|null 返回值：
     *               - 同步：EventResponse 对象（包含执行结果）
     *               - 异步/队列：null（不阻塞无返回）
     *               - 延迟：int|false（定时器ID）
     */
    public static function emit(
        string       $event,
        array|object $args = [],
        bool         $async = true,
        int          $delay = 0,
        bool         $queue = false
    ): int|EventResponse|null|false
    {
        $listeners = self::getSortedListeners($event);
        if ($listeners === null) {
            return $delay > 0 ? false : null;
        }

        $argsArray = (array)$args;
        $parentContext = CoroutineContext::capture();

        // 有延迟时，使用 Timer 延迟执行
        if ($delay > 0) {
            return self::scheduleDelayed($listeners, $argsArray, $parentContext, $delay, $async, $queue);
        }

        // 无延迟，立即执行
        return self::executeImmediate($event, $listeners, $argsArray, $parentContext, $async, $queue);
    }

    /**
     * 立即执行（无延迟）
     */
    private static function executeImmediate(
        string $event,
        array  $listeners,
        array  $args,
        array  $parentContext,
        bool   $async,
        bool   $queue
    ): ?EventResponse
    {
        // 队列模式优先（queue=true 时，async 不影响，因为队列本身保证串行）
        if ($queue) {
            EventQueue::push($listeners, $args, $parentContext);
            return null;
        }

        // 异步模式：不阻塞，监听器并行执行
        if ($async) {
            EventExecutor::runAsync($listeners, $args, $parentContext);
            return null;
        }

        // 同步模式：阻塞执行，返回结果
        $response = EventExecutor::runSync($listeners, $args, true);
        if ($response !== null) {
            $response->event = $event;
        }
        return $response;
    }

    /**
     * 延迟执行
     */
    private static function scheduleDelayed(
        array $listeners,
        array $args,
        array $parentContext,
        int   $delayMs,
        bool  $async,
        bool  $queue
    ): int|false
    {
        return Timer::after($delayMs, function () use ($listeners, $args, $parentContext, $async, $queue) {
            Coroutine::create(function () use ($listeners, $args, $parentContext, $async, $queue) {
                CoroutineContext::restore($parentContext);

                // 队列模式优先
                if ($queue) {
                    EventQueue::push($listeners, $args, $parentContext);
                    return;
                }

                // 异步模式：监听器并行执行
                if ($async) {
                    EventExecutor::runAsync($listeners, $args, $parentContext);
                    return;
                }

                // 同步模式：串行执行（在Timer协程中）
                EventExecutor::runSync($listeners, $args);
            });
        });
    }

    /**
     * 获取排序后的监听器列表
     *
     * @param string $event 事件名称
     * @return array|null 排序后的监听器列表，无监听器返回null
     */
    private static function getSortedListeners(string $event): ?array
    {
        if (!isset(self::$listeners[$event])) {
            return null;
        }

        $listeners = self::$listeners[$event];
        usort($listeners, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $listeners;
    }

    /**
     * 比较两个监听器是否相同
     */
    private static function isSameListener($listener1, $listener2): bool
    {
        if (is_callable($listener1) && is_callable($listener2)) {
            return $listener1 === $listener2;
        }
        if (is_array($listener1) && is_array($listener2)) {
            return $listener1 === $listener2;
        }
        return false;
    }

    public static function onMaps(): void
    {
        foreach (EventMap::EVENTS as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                try {
                    $priority = $listener['priority'] ?? 10;
                    Event::on($eventName, $listener['run'], $priority);
                } catch (Exception $e) {
                    Log::saveException($e, 'event-on');
                }
            }
        }
    }

    public static function offMaps(): void
    {
        foreach (EventMap::EVENTS as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                Event::off($eventName, $listener['run']);
            }
        }
    }
}
