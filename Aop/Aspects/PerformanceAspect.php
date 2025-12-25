<?php
declare(strict_types=1);

namespace Swlib\Aop\Aspects;

use Attribute;
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Utils\Log;
use Throwable;

/**
 * 性能监控切面
 *
 * 监控方法执行性能，识别性能瓶颈
 *
 * @example
 * #[PerformanceAspect(threshold: 1000.0)]
 * public function complexCalculation() { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class PerformanceAspect extends AbstractAspect implements ProxyAttributeInterface
{

    /**
     * @var array 性能统计数据
     */
    private static array $stats = [];

    /**
     * @var array 当前执行的开始时间
     */
    private array $startTimes = [];


    /**
     * 构造函数
     *
     * @param float $threshold 性能告警阈值（毫秒），默认 1000ms
     * @param bool $logAll 是否记录所有调用，默认 false（只记录超过阈值的）
     * @param int $priority 执行优先级，多个注解时需显式指定
     */
    public function __construct(
        public float $threshold = 1000.0, // 性能告警阈值（毫秒）
        public bool  $logAll = false, // 是否记录所有调用
        public int   $priority = 0,
        public bool   $async = false
    )
    {

    }

    /**
     * 前置通知 - 记录开始时间
     *
     * @param JoinPoint $joinPoint
     * @return void
     */
    public function before(JoinPoint $joinPoint): void
    {
        $key = $this->getKey($joinPoint);
        $this->startTimes[$key] = microtime(true);
    }

    /**
     * 后置通知 - 计算执行时间并记录
     *
     * @param JoinPoint $joinPoint
     * @param mixed $result
     * @return void
     */
    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        $key = $this->getKey($joinPoint);

        if (!isset($this->startTimes[$key])) {
            return;
        }

        $duration = (microtime(true) - $this->startTimes[$key]) * 1000; // 转换为毫秒
        unset($this->startTimes[$key]);

        // 记录统计信息
        $this->recordStats($joinPoint->getSignature(), $duration);

        // 如果超过阈值或需要记录所有调用，则记录日志
        if ($this->logAll || $duration > $this->threshold) {
            $logModule = $duration > $this->threshold ? 'performance_warning' : 'performance_info';
            $message = sprintf(
                "性能监控: %s | 耗时: %.2fms%s",
                $joinPoint->getSignature(),
                $duration,
                $duration > $this->threshold ? ' [超过阈值]' : ''
            );

            Log::save($message, $logModule);
        }
    }

    /**
     * 异常通知 - 清理开始时间
     *
     * @param JoinPoint $joinPoint
     * @param Throwable $exception
     * @return void
     */
    public function afterThrowing(JoinPoint $joinPoint, Throwable $exception): void
    {
        $key = $this->getKey($joinPoint);

        if (isset($this->startTimes[$key])) {
            $duration = (microtime(true) - $this->startTimes[$key]) * 1000;
            unset($this->startTimes[$key]);

            // 记录异常情况下的执行时间
            $this->recordStats($joinPoint->getSignature(), $duration, true);
        }
    }

    /**
     * 记录统计信息
     *
     * @param string $signature 方法签名
     * @param float $duration 执行时间（毫秒）
     * @param bool $isException 是否为异常情况
     * @return void
     */
    private function recordStats(string $signature, float $duration, bool $isException = false): void
    {
        if (!isset(self::$stats[$signature])) {
            self::$stats[$signature] = [
                'count' => 0,
                'total_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
                'avg_time' => 0.0,
                'exception_count' => 0,
            ];
        }

        self::$stats[$signature]['count']++;
        self::$stats[$signature]['total_time'] += $duration;
        self::$stats[$signature]['min_time'] = min(self::$stats[$signature]['min_time'], $duration);
        self::$stats[$signature]['max_time'] = max(self::$stats[$signature]['max_time'], $duration);
        self::$stats[$signature]['avg_time'] = self::$stats[$signature]['total_time'] / self::$stats[$signature]['count'];

        if ($isException) {
            self::$stats[$signature]['exception_count']++;
        }
    }

    /**
     * 获取唯一键
     *
     * @param JoinPoint $joinPoint
     * @return string
     */
    private function getKey(JoinPoint $joinPoint): string
    {
        return spl_object_id($joinPoint->target) . '::' . $joinPoint->methodName;
    }

    /**
     * 获取性能统计信息
     *
     * @param string|null $signature 方法签名，null 返回所有
     * @return array
     */
    public static function getStats(?string $signature = null): array
    {
        if ($signature !== null) {
            return self::$stats[$signature] ?? [];
        }
        return self::$stats;
    }

    /**
     * 获取最慢的方法列表
     *
     * @param int $limit 返回数量，默认 10
     * @return array
     */
    public static function getSlowestMethods(int $limit = 10): array
    {
        $stats = self::$stats;

        // 按平均时间排序
        uasort($stats, fn($a, $b) => $b['avg_time'] <=> $a['avg_time']);

        return array_slice($stats, 0, $limit, true);
    }

    /**
     * 获取调用最频繁的方法列表
     *
     * @param int $limit 返回数量，默认 10
     * @return array
     */
    public static function getMostCalledMethods(int $limit = 10): array
    {
        $stats = self::$stats;

        // 按调用次数排序
        uasort($stats, fn($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($stats, 0, $limit, true);
    }

    /**
     * 重置统计信息
     *
     * @return void
     */
    public static function resetStats(): void
    {
        self::$stats = [];
    }

    /**
     * 打印性能报告
     *
     * @return string
     */
    public static function getReport(): string
    {
        $report = "\n=== 性能监控报告 ===\n\n";

        $report .= "最慢的方法（按平均时间）:\n";
        foreach (self::getSlowestMethods(5) as $signature => $stat) {
            $report .= sprintf(
                "  %s\n    调用次数: %d | 平均: %.2fms | 最小: %.2fms | 最大: %.2fms\n",
                $signature,
                $stat['count'],
                $stat['avg_time'],
                $stat['min_time'],
                $stat['max_time']
            );
        }

        $report .= "\n调用最频繁的方法:\n";
        foreach (self::getMostCalledMethods(5) as $signature => $stat) {
            $report .= sprintf(
                "  %s\n    调用次数: %d | 平均: %.2fms\n",
                $signature,
                $stat['count'],
                $stat['avg_time']
            );
        }

        return $report;
    }

    public function handle(array $ctx, callable $next): mixed
    {
        $joinPoint = new JoinPoint($ctx['target'], $ctx['meta']['method'], $ctx['arguments']);
        return $this->around($joinPoint, static fn() => $next($ctx));
    }
}

