<?php
declare(strict_types=1);

namespace Swlib\Table\Trait;

use Generate\ConfigEnum;
use RuntimeException;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Utils\Log;
use Swoole\Coroutine;

/**
 * 连接池公共功能 Trait
 * 提供超时检测、嵌套深度检测、调用栈获取等公共功能
 */
trait PoolConnectionTrait
{
    /**
     * 连接池最小值
     */
    private const int MIN_POOL_SIZE = 5;

    /**
     * 获取连接超时时间（秒）
     */
    private const float GET_TIMEOUT = 5.0;

    /**
     * 最大嵌套深度
     */
    private const int MAX_NEST_DEPTH = 3;

    /**
     * 获取调用栈
     */
    private static function getCallStack(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $stack = [];
        foreach ($trace as $t) {
            $class = $t['class'] ?? '';
            $type = $t['type'] ?? '';
            $function = $t['function'] ?? '';
            $file = $t['file'] ?? '';
            $line = $t['line'] ?? '';
            
            $stack[] = "  → $class$type$function ($file:$line)";
        }
        return implode("\n", $stack);
    }

    /**
     * 检测并记录嵌套深度
     *
     * @param string $contextKey 协程上下文键名（如 'redis_call_depth' 或 'mysql_call_depth'）
     * @param int $poolSize 连接池大小
     * @param string $poolType 连接池类型（用于错误提示）
     * @return int 当前深度
     * @throws RuntimeException 当嵌套深度 >= 连接池大小时抛出异常（必然死锁）
     */
    private static function checkNestDepth(string $contextKey, int $poolSize, string $poolType): int
    {
        $context = Coroutine::getContext();
        $depth = ($context[$contextKey] ?? 0) + 1;
        $context[$contextKey] = $depth;

        // 如果嵌套深度 >= 连接池大小，必然死锁
        if ($depth >= $poolSize) {
            $message = "严重错误：检测到必然死锁！\n" .
                       "连接池类型: Pool$poolType\n" .
                       "连接池大小: $poolSize\n" .
                       "当前嵌套深度: $depth\n" .
                       "原因：嵌套深度已达到或超过连接池大小，无法获取更多连接\n" .
                       "解决方案：\n" .
                       "  1. 增加连接池大小（建议至少 " . ($depth + 5) . "）\n" .
                       "  2. 重构代码，避免深度嵌套调用\n" .
                       "  3. 检查是否有递归调用\n" .
                       "调用栈：\n" . self::getCallStack();

            // 记录严重错误日志
            $logModule = strtolower($poolType) . '_pool';
            Log::save($message, $logModule);

            // 开发模式下在控制台输出
            $isDev = ConfigEnum::get('APP_PROD') !== true;
            if ($isDev) {
                ConsoleColor::writeErrorHighlight($message);
            }

            throw new RuntimeException($message);
        }

        return $depth;
    }

    /**
     * 减少嵌套深度
     * 
     * @param string $contextKey 协程上下文键名
     * @param int $depth 当前深度
     */
    private static function decreaseNestDepth(string $contextKey, int $depth): void
    {
        $context = Coroutine::getContext();
        $context[$contextKey] = $depth - 1;
    }

    /**
     * 记录嵌套深度警告
     * 
     * @param int $depth 嵌套深度
     * @param string $poolType 连接池类型（'Redis' 或 'MySQL'）
     * @param string $logModule 日志模块名（'redis_pool' 或 'mysql_pool'）
     * @param string $extra 额外信息（如数据库名）
     */
    private static function logNestWarning(int $depth, string $poolType, string $logModule, string $extra = ''): void
    {
        $extraInfo = $extra ? ", $extra" : '';
        $message = "检测到深度嵌套的 Pool$poolType 调用 (深度: $depth$extraInfo)\n" .
                   "这可能导致连接池耗尽，建议重构代码避免嵌套\n" .
                   "调用栈:\n" . self::getCallStack();
        
        // 记录日志
        Log::save($message, $logModule);
        
        // 开发模式下在控制台输出警告
        $isDev = ConfigEnum::get('APP_PROD') !== true;
        if ($isDev) {
            ConsoleColor::writeWarning($message);
        }
    }

    /**
     * 检查是否超时
     * 
     * @param float $startTime 开始时间
     * @return float 已经过的时间
     */
    private static function checkTimeout(float $startTime): float
    {
        return microtime(true) - $startTime;
    }

    /**
     * 抛出超时异常
     * 
     * @param float $elapsed 已经过的时间
     * @param string $poolType 连接池类型（'Redis' 或 'MySQL'）
     * @param string $contextKey 协程上下文键名
     * @param int $poolSize 连接池大小
     * @param string $extra 额外信息
     */
    private static function throwTimeoutException(
        float $elapsed,
        string $poolType,
        string $contextKey,
        int $poolSize,
        string $extra = ''
    ): void {
        $context = Coroutine::getContext();
        $depth = $context[$contextKey] ?? 0;
        
        $extraInfo = $extra ? "\n$extra" : '';
        $message = "获取 $poolType 连接超时 (" . round($elapsed, 2) . "秒)$extraInfo\n" .
                   "当前连接池大小: $poolSize\n" .
                   "当前嵌套深度: $depth\n" .
                   "可能原因:\n" .
                   "  1. 存在嵌套的 Pool$poolType 调用（检查调用栈）\n" .
                   "  2. 连接池配置过小（建议 >= 10）\n" .
                   "  3. 某个操作占用连接时间过长\n" .
                   "调用栈:\n" . self::getCallStack();
        
        throw new RuntimeException($message);
    }
}

