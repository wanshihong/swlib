<?php

declare(strict_types=1);

namespace Swlib\Event\Helper;

/**
 * 监听器执行结果
 * 
 * 封装单个事件监听器的执行结果信息，包括执行时间、返回值等
 */
readonly class ListenerResult
{
    /**
     * 监听器索引
     * 
     * 表示该监听器在监听器列表中的位置（从0开始）
     */
    public int $listenerIndex;

    /**
     * 监听器信息
     * 
     * 监听器的描述信息，通常是类名::方法名 或 Closure
     */
    public string $listenerInfo;

    /**
     * 监听器优先级
     * 
     * 数值越小优先级越高，优先执行
     */
    public int $priority;

    /**
     * 监听器返回值
     * 
     * 监听器执行后的返回结果，如果返回 false 则会停止事件传播
     */
    public mixed $result;

    /**
     * 执行开始时间戳
     * 
     * 监听器开始执行的 Unix 时间戳（微秒精度）
     */
    public float $executedAt;

    /**
     * 执行耗时（秒）
     * 
     * 监听器执行所花费的时间，精确到微秒
     */
    public float $executionTime;

    /**
     * 构造函数
     *
     * @param int $listenerIndex 监听器索引
     * @param string $listenerInfo 监听器信息
     * @param int $priority 优先级
     * @param mixed $result 执行结果
     * @param float $executedAt 执行开始时间戳
     * @param float $executionTime 执行耗时（秒）
     */
    public function __construct(
        int $listenerIndex,
        string $listenerInfo,
        int $priority,
        mixed $result,
        float $executedAt,
        float $executionTime
    ) {
        $this->listenerIndex = $listenerIndex;
        $this->listenerInfo = $listenerInfo;
        $this->priority = $priority;
        $this->result = $result;
        $this->executedAt = $executedAt;
        $this->executionTime = $executionTime;
    }

    /**
     * 检查监听器是否停止了事件传播
     * 
     * 当监听器返回 false 时，事件传播会被停止
     *
     * @return bool 如果返回 false 则表示停止传播
     */
    public function isStopPropagation(): bool
    {
        return $this->result === false;
    }

    /**
     * 获取执行耗时（毫秒）
     *
     * @return float 执行耗时，单位毫秒
     */
    public function getExecutionTimeMs(): float
    {
        return $this->executionTime * 1000;
    }

    /**
     * 转换为数组格式
     * 
     * 用于序列化或调试输出
     *
     * @return array<string, mixed> 包含所有属性的关联数组
     */
    public function toArray(): array
    {
        return [
            'listener_index' => $this->listenerIndex,
            'listener_info' => $this->listenerInfo,
            'priority' => $this->priority,
            'result' => $this->result,
            'executed_at' => $this->executedAt,
            'execution_time' => $this->executionTime,
        ];
    }
}

