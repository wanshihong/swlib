<?php

declare(strict_types=1);

namespace Swlib\Event\Helper;

use Swlib\Coroutine\CoroutineContext;
use Swlib\Utils\Log;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * 事件队列管理器
 * 使用 Swoole\Coroutine\Channel 实现事件队列化执行
 * 队列空闲超时后自动关闭，用户无需手动管理
 */
class EventQueue
{
    /**
     * 事件队列 Channel 实例
     * @var Channel|null
     */
    private static ?Channel $channel = null;

    /**
     * 队列是否正在运行
     * @var bool
     */
    private static bool $running = false;

    /**
     * 空闲超时时间（秒）
     * 队列空闲超过此时间后自动关闭
     */
    private const float IDLE_TIMEOUT = 5.0;

    /**
     * 初始化事件队列
     *
     * @param int $capacity 队列容量，默认 1024
     * @return void
     */
    public static function init(int $capacity = 1024): void
    {
        if (self::$channel !== null && self::$running) {
            return;
        }

        self::$channel = new Channel($capacity);
        self::$running = true;
        self::startConsumer();
    }

    /**
     * 启动队列消费者
     * 空闲超时后自动关闭，无需用户手动管理
     *
     * @return void
     */
    private static function startConsumer(): void
    {
        Coroutine::create(function () {
            while (self::$running) {
                // 使用空闲超时，超时后自动关闭队列
                $task = self::$channel->pop(self::IDLE_TIMEOUT);
                if ($task === false) {
                    // 超时或 Channel 已关闭
                    if (self::$channel->length() === 0) {
                        // 队列为空且超时，自动关闭
                        self::doClose();
                        break;
                    }
                    continue;
                }

                try {
                    $listeners = $task['listeners'];
                    $args = $task['args'];
                    $parentContext = $task['context'];

                    // 恢复协程上下文
                    CoroutineContext::restore($parentContext);

                    // 按顺序执行监听器
                    foreach ($listeners as $item) {
                        $result = EventExecutor::run($item['listener'], $args);
                        if ($result === false) {
                            break;
                        }
                    }
                } catch (Throwable $e) {
                    Log::saveException($e, 'event-queue');
                }
            }
        });
    }

    /**
     * 内部关闭方法
     */
    private static function doClose(): void
    {
        self::$running = false;
        if (self::$channel !== null) {
            self::$channel->close();
            self::$channel = null;
        }
    }

    /**
     * 将事件推入队列
     *
     * @param array $listeners 监听器列表
     * @param array $args 参数
     * @param array $parentContext 父协程上下文
     * @return bool 是否成功推入队列
     */
    public static function push(array $listeners, array $args, array $parentContext): bool
    {
        if (self::$channel === null) {
            self::init();
        }

        return self::$channel->push([
            'listeners' => $listeners,
            'args' => $args,
            'context' => $parentContext
        ]);
    }

    /**
     * 关闭事件队列
     *
     * @return void
     */
    public static function close(): void
    {
        self::$running = false;
        if (self::$channel !== null) {
            self::$channel->close();
            self::$channel = null;
        }
    }

    /**
     * 获取队列当前长度
     *
     * @return int
     */
    public static function length(): int
    {
        if (self::$channel === null) {
            return 0;
        }
        return self::$channel->length();
    }


}

