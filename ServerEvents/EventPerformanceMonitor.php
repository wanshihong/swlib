<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Utils\Log;

/**
 * 事件性能监控器
 * 提供详细的事件执行性能统计和监控
 */
class EventPerformanceMonitor
{
    /**
     * 性能统计数据
     * @var array<string, array{count: int, total_time: float, max_time: float, min_time: float, errors: int}>
     */
    private static array $stats = [];

    /**
     * 慢事件阈值（毫秒）
     */
    private const int SLOW_EVENT_THRESHOLD = 100;

    /**
     * 记录事件执行开始
     *
     * @param string $eventName
     * @return string 唯一标识符
     */
    public static function startEvent(string $eventName): string
    {
        $traceId = uniqid($eventName . '_', true);
        $GLOBALS['event_performance'][$traceId] = [
            'event' => $eventName,
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];

        return $traceId;
    }

    /**
     * 记录事件执行结束
     *
     * @param string $traceId
     * @param bool $hasError 是否发生错误
     */
    public static function endEvent(string $traceId, bool $hasError = false): void
    {
        if (!isset($GLOBALS['event_performance'][$traceId])) {
            return;
        }

        $data = $GLOBALS['event_performance'][$traceId];
        $eventName = $data['event'];
        $startTime = $data['start_time'];
        $memoryStart = $data['memory_start'];

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // 毫秒
        $memoryUsed = memory_get_usage(true) - $memoryStart;
        $memoryPeak = memory_get_peak_usage(true);

        // 更新统计数据
        self::updateStats($eventName, $executionTime, $hasError);

        // 记录慢事件
        if ($executionTime > self::SLOW_EVENT_THRESHOLD) {
            Log::warning("Slow event detected", [
                'event' => $eventName,
                'execution_time' => round($executionTime, 2) . 'ms',
                'memory_used' => self::formatBytes($memoryUsed),
                'memory_peak' => self::formatBytes($memoryPeak),
                'trace_id' => $traceId
            ]);
        }

        // 记录错误事件
        if ($hasError) {
            Log::error("Event execution failed", [
                'event' => $eventName,
                'execution_time' => round($executionTime, 2) . 'ms',
                'trace_id' => $traceId
            ]);
        }

        // 清理临时数据
        unset($GLOBALS['event_performance'][$traceId]);
    }

    /**
     * 更新性能统计
     *
     * @param string $eventName
     * @param float $executionTime
     * @param bool $hasError
     */
    private static function updateStats(string $eventName, float $executionTime, bool $hasError): void
    {
        if (!isset(self::$stats[$eventName])) {
            self::$stats[$eventName] = [
                'count' => 0,
                'total_time' => 0.0,
                'max_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'errors' => 0
            ];
        }

        $stats = &self::$stats[$eventName];
        $stats['count']++;
        $stats['total_time'] += $executionTime;
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['min_time'] = min($stats['min_time'], $executionTime);

        if ($hasError) {
            $stats['errors']++;
        }
    }

    /**
     * 获取事件性能统计
     *
     * @param string|null $eventName 指定事件名，null则返回所有
     * @return array
     */
    public static function getStats(?string $eventName = null): array
    {
        if ($eventName !== null) {
            return self::$stats[$eventName] ?? [];
        }

        return array_map(function ($stats) {
            return [
                'count' => $stats['count'],
                'total_time' => round($stats['total_time'], 2),
                'avg_time' => round($stats['total_time'] / max($stats['count'], 1), 2),
                'max_time' => round($stats['max_time'], 2),
                'min_time' => round($stats['min_time'], 2),
                'errors' => $stats['errors'],
                'error_rate' => round(($stats['errors'] / max($stats['count'], 1)) * 100, 2) . '%'
            ];
        }, self::$stats);
    }

    /**
     * 重置性能统计
     */
    public static function resetStats(): void
    {
        self::$stats = [];
    }

    /**
     * 获取性能报告
     *
     * @return string
     */
    public static function getReport(): string
    {
        $stats = self::getStats();
        if (empty($stats)) {
            return "No performance data available";
        }

        $report = "Event Performance Report\n";
        $report .= "========================\n\n";

        foreach ($stats as $eventName => $data) {
            $report .= "Event: $eventName\n";
            $report .= "  Count: {$data['count']}\n";
            $report .= "  Avg Time: {$data['avg_time']}ms\n";
            $report .= "  Max Time: {$data['max_time']}ms\n";
            $report .= "  Min Time: {$data['min_time']}ms\n";
            $report .= "  Total Time: {$data['total_time']}ms\n";
            $report .= "  Errors: {$data['errors']} ({$data['error_rate']})\n";
            $report .= "\n";
        }

        return $report;
    }

    /**
     * 格式化字节数
     *
     * @param int $bytes
     * @return string
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
