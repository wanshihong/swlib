<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Utils\Log;
use Swoole\Server;
use Throwable;

/**
 * Swoole 服务器事件管理器
 * 统一管理所有服务器事件的绑定和处理
 */
class ServerEventManager
{
    /**
     * 事件处理器实例缓存
     * @var array<string, object>
     */
    private static array $handlerInstances = [];

    /**
     * 事件处理器映射（按绑定顺序排列）
     * 格式: [eventName => [handlerClass, constructorArgs, canReuse]]
     */
    private const array EVENT_HANDLERS = [
        'start' => [OnStartEvent::class, [], false],         // 单次执行，不重用
        'workerStart' => [OnWorkerStartEvent::class, [], false], // 每个worker独立
        'workerStop' => [OnWorkerStopEvent::class, [], false],   // 每个worker独立
        'receive' => [OnReceiveEvent::class, [], true],      // 可以重用
        'request' => [OnRequestEvent::class, [], true],      // 可以重用
        'open' => [OnOpenEvent::class, [], true],            // 可以重用
        'message' => [OnMessageEvent::class, [], true],      // 可以重用
        'close' => [OnCloseEvent::class, [], true],          // 可以重用
        'task' => [OnTaskEvent::class, [], true],            // 可以重用
        'finish' => [OnFinishEvent::class, [], true],        // 可以重用
        'pipeMessage' => [OnPipeMessageEvent::class, [], true],  // 可以重用
        'shutdown' => [OnShutdownEvent::class, [], false],   // 单次执行，不重用
    ];

    /**
     * 绑定所有服务器事件
     *
     * @param Server $server Swoole 服务器实例
     * @throws Throwable
     */
    public static function bindEvents(Server $server): void
    {
        try {
            // 按 EVENT_HANDLERS 定义的顺序绑定所有事件
            foreach (self::EVENT_HANDLERS as $eventName => $handlerConfig) {
                self::bindSingleEvent($server, $eventName);
            }

            Log::info('All server events bound successfully');

        } catch (Throwable $e) {
            Log::saveException($e, 'ServerEventManager::bindEvents');
            throw $e;
        }
    }

    /**
     * 绑定单个事件
     *
     * @param Server $server
     * @param string $eventName
     * @throws Throwable
     */
    private static function bindSingleEvent(Server $server, string $eventName): void
    {
        [$handlerClass, $constructorArgs, $canReuse] = self::EVENT_HANDLERS[$eventName];

        try {
            // 创建或获取处理器实例
            $handler = self::createHandlerInstance($handlerClass, $constructorArgs, $canReuse);

            // 绑定事件，使用统一的事件处理包装器
            $server->on($eventName, function (...$args) use ($handler, $eventName) {
                self::handleEvent($handler, $eventName, ...$args);
            });

        } catch (Throwable $e) {
            Log::error("Failed to bind event '$eventName': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 创建或获取事件处理器实例
     *
     * @param string $handlerClass
     * @param array $constructorArgs
     * @param bool $canReuse 是否可以重用实例
     * @return object
     * @throws Throwable
     */
    private static function createHandlerInstance(string $handlerClass, array $constructorArgs, bool $canReuse): object
    {
        $instanceKey = $handlerClass;

        // 如果可以重用实例，尝试从缓存获取
        if ($canReuse && isset(self::$handlerInstances[$instanceKey])) {
            return self::$handlerInstances[$instanceKey];
        }

        // 直接使用配置中定义的构造函数参数
        $instance = new $handlerClass(...$constructorArgs);

        // 如果可以重用，缓存实例
        if ($canReuse) {
            self::$handlerInstances[$instanceKey] = $instance;
        }

        return $instance;
    }

    /**
     * 统一的事件处理包装器
     * 提供统一的异常处理和性能监控
     *
     * @param object $handler
     * @param string $eventName
     * @param mixed ...$args
     * @throws Throwable
     */
    private static function handleEvent(object $handler, string $eventName, ...$args): void
    {
        $traceId = EventPerformanceMonitor::startEvent($eventName);
        $hasError = false;

        try {
            $handler->handle(...$args);
        } catch (Throwable $e) {
            $hasError = true;
            Log::saveException($e, "Event '$eventName' failed");

            // 对于关键事件，可以选择重新抛出异常
            if (in_array($eventName, ['start', 'workerStart', 'shutdown'])) {
                EventPerformanceMonitor::endEvent($traceId, true);
                throw $e;
            }
        } finally {
            EventPerformanceMonitor::endEvent($traceId, $hasError);
        }
    }


    /**
     * 清理实例缓存
     * 在服务器关闭时调用，避免内存泄漏
     */
    public static function clearInstanceCache(): void
    {
        self::$handlerInstances = [];
        Log::info('Event handler instance cache cleared');
    }

}
