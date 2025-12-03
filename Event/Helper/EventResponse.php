<?php

declare(strict_types=1);

namespace Swlib\Event\Helper;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * 事件执行响应
 * 
 * 封装事件同步执行的完整结果信息，包括执行统计、时间信息和各监听器的执行结果
 * 
 * @implements IteratorAggregate<int, ListenerResult>
 */
class EventResponse implements IteratorAggregate
{
    /**
     * 事件名称
     * 
     * 触发的事件名称标识
     */
    public string $event = '';

    /**
     * 监听器总数
     * 
     * 注册到该事件的监听器总数量
     */
    public readonly int $totalListeners;

    /**
     * 已执行的监听器数量
     * 
     * 实际执行的监听器数量（可能因停止传播而少于总数）
     */
    public readonly int $executedListeners;

    /**
     * 是否提前停止
     * 
     * 如果某个监听器返回 false，事件传播会被停止，此值为 true
     */
    public readonly bool $stoppedEarly;

    /**
     * 执行开始时间戳
     * 
     * 事件开始执行的 Unix 时间戳（微秒精度）
     */
    public readonly float $executionStartTime;

    /**
     * 执行结束时间戳
     * 
     * 事件执行完成的 Unix 时间戳（微秒精度）
     */
    public readonly float $executionEndTime;

    /**
     * 总执行耗时（秒）
     * 
     * 从开始到结束的总耗时，精确到微秒
     */
    public readonly float $executionDuration;

    /**
     * 各监听器的执行结果列表
     * 
     * @var ListenerResult[] 按执行顺序排列的监听器结果数组
     */
    public readonly array $results;

    /**
     * 构造函数
     *
     * @param int $totalListeners 监听器总数
     * @param int $executedListeners 已执行的监听器数量
     * @param bool $stoppedEarly 是否提前停止
     * @param float $executionStartTime 执行开始时间戳
     * @param float $executionEndTime 执行结束时间戳
     * @param float $executionDuration 总执行耗时（秒）
     * @param ListenerResult[] $results 各监听器的执行结果
     */
    public function __construct(
        int $totalListeners,
        int $executedListeners,
        bool $stoppedEarly,
        float $executionStartTime,
        float $executionEndTime,
        float $executionDuration,
        array $results
    ) {
        $this->totalListeners = $totalListeners;
        $this->executedListeners = $executedListeners;
        $this->stoppedEarly = $stoppedEarly;
        $this->executionStartTime = $executionStartTime;
        $this->executionEndTime = $executionEndTime;
        $this->executionDuration = $executionDuration;
        $this->results = $results;
    }

    /**
     * 获取总执行耗时（毫秒）
     *
     * @return float 执行耗时，单位毫秒
     */
    public function getExecutionDurationMs(): float
    {
        return $this->executionDuration * 1000;
    }

    /**
     * 检查是否所有监听器都已执行
     *
     * @return bool 如果所有监听器都已执行返回 true
     */
    public function isComplete(): bool
    {
        return $this->executedListeners === $this->totalListeners;
    }

    /**
     * 获取指定索引的监听器结果
     *
     * @param int $index 监听器索引
     * @return ListenerResult|null 监听器结果，不存在时返回 null
     */
    public function getResult(int $index): ?ListenerResult
    {
        return $this->results[$index] ?? null;
    }

    /**
     * 获取最后一个执行的监听器结果
     *
     * @return ListenerResult|null 最后一个监听器结果，无结果时返回 null
     */
    public function getLastResult(): ?ListenerResult
    {
        if (empty($this->results)) {
            return null;
        }
        return $this->results[count($this->results) - 1];
    }

    /**
     * 获取所有监听器的返回值数组
     *
     * @return array<int, mixed> 所有监听器返回值的数组
     */
    public function getAllReturnValues(): array
    {
        return array_map(fn(ListenerResult $r) => $r->result, $this->results);
    }

    /**
     * 实现 IteratorAggregate 接口，支持 foreach 遍历
     *
     * @return Traversable<int, ListenerResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    /**
     * 转换为数组格式
     * 
     * 用于序列化或调试输出，保持与原数组格式的兼容性
     *
     * @return array<string, mixed> 包含所有属性的关联数组
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'total_listeners' => $this->totalListeners,
            'executed_listeners' => $this->executedListeners,
            'stopped_early' => $this->stoppedEarly,
            'execution_start_time' => $this->executionStartTime,
            'execution_end_time' => $this->executionEndTime,
            'execution_duration' => $this->executionDuration,
            'results' => array_map(fn(ListenerResult $r) => $r->toArray(), $this->results),
        ];
    }
}

