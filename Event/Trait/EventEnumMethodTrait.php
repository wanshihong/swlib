<?php

namespace Swlib\Event\Trait;

use Exception;
use Swlib\Event\Attribute\Event;
use Swlib\Event\Helper\EventResponse;

trait EventEnumMethodTrait
{
    /**
     * 注册事件监听器
     *
     * @param array $listener [className, methodName]
     * @throws Exception
     */
    public function on(array $listener): void
    {
        Event::on($this->name, $listener);
    }

    /**
     * 移除事件监听器
     *
     * @param array $listener [className, methodName]
     * @throws Exception
     */
    public function off(array $listener): void
    {
        Event::off($this->name, $listener);
    }

    /**
     * 触发事件（统一入口）
     *
     * 支持参数自由组合：
     * - async: 不阻塞，监听器并行执行
     * - queue: 不阻塞，监听器串行执行（队列）
     * - delay: 延迟后执行
     *
     * 使用示例：
     * ```php
     * // 1. 同步执行（默认）- 阻塞，返回结果
     * $result = EventEnum::UserLogin->emit($args);
     *
     * // 2. 异步执行 - 不阻塞，并行
     * EventEnum::UserLogin->emit($args, async: true);
     *
     * // 3. 队列执行 - 不阻塞，串行
     * EventEnum::UserLogin->emit($args, queue: true);
     *
     * // 4. 延迟执行
     * EventEnum::UserLogin->emit($args, delay: 1000);
     *
     * // 5. 延迟 + 异步
     * EventEnum::UserLogin->emit($args, delay: 1000, async: true);
     *
     * // 6. 延迟 + 队列
     * EventEnum::UserLogin->emit($args, delay: 1000, queue: true);
     * ```
     *
     * @param array|object $args 参数
     * @param bool $async 是否异步（不阻塞，并行）
     * @param int $delay 延迟时间（毫秒）
     * @param bool $queue 是否队列（不阻塞，串行）
     * @return EventResponse|int|false|null
     */
    public function emit(
        array|object $args = [],
        bool         $async = true,
        int          $delay = 0,
        bool         $queue = false
    ): EventResponse|int|null|false
    {
        return Event::emit($this->name, $args, $async, $delay, $queue);
    }
}
