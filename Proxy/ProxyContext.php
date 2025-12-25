<?php
declare(strict_types=1);

namespace Swlib\Proxy;

use Swoole\Coroutine;

/**
 * 代理调度上下文管理器
 *
 * 使用栈结构管理协程上下文，支持嵌套调用。
 * 每次 ProxyDispatcher::dispatch() 调用会 push 一个新的 ProxyResult，
 * 调用结束后可以通过 pop() 获取该次调度的链路信息。
 *
 * 使用示例：
 * ```php
 * $user = $service->getUserInfo($id);  // 正常调用，返回原始类型
 *
 * // 获取最近一次调度的链路信息
 * $context = ProxyContext::pop();
 * $queueId = $context->getProxyResult(QueueAttribute::class);
 * $executionTime = $context->getProxyResult(PerformanceAspect::class);
 * $realResult = $context->getResult();
 * ```
 */
final class ProxyContext
{
    private const string CONTEXT_KEY = '__proxy_context_stack__';

    /**
     * 非协程环境的上下文栈
     * @var array<ProxyResult>
     */
    private static array $nonCoroStack = [];

    /**
     * 获取当前协程的上下文栈
     *
     * @return array<ProxyResult>
     */
    private static function getStack(): array
    {
        $ctx = Coroutine::getContext();
        if ($ctx === null) {
            // 非协程环境，使用类静态属性
            return self::$nonCoroStack;
        }
        return $ctx[self::CONTEXT_KEY] ?? [];
    }

    /**
     * 设置当前协程的上下文栈
     *
     * @param array<ProxyResult> $stack
     */
    private static function setStack(array $stack): void
    {
        $ctx = Coroutine::getContext();
        if ($ctx === null) {
            self::$nonCoroStack = $stack;
            return;
        }
        $ctx[self::CONTEXT_KEY] = $stack;
    }

    /**
     * 压入新的调度上下文
     *
     * @return ProxyResult 返回新创建的上下文对象
     */
    public static function push(): ProxyResult
    {
        $result = new ProxyResult();
        $stack = self::getStack();
        $stack[] = $result;
        self::setStack($stack);
        return $result;
    }

    /**
     * 弹出最近一次调度的上下文
     *
     * @return ProxyResult|null 如果栈为空返回 null
     */
    public static function pop(): ?ProxyResult
    {
        $stack = self::getStack();
        if (empty($stack)) {
            return null;
        }
        $result = array_pop($stack);
        self::setStack($stack);
        return $result;
    }

    /**
     * 获取当前调度上下文（不弹出）
     *
     * @return ProxyResult|null 如果栈为空返回 null
     */
    public static function current(): ?ProxyResult
    {
        $stack = self::getStack();
        if (empty($stack)) {
            return null;
        }
        return $stack[count($stack) - 1];
    }

    /**
     * 获取栈深度
     *
     * @return int
     */
    public static function depth(): int
    {
        return count(self::getStack());
    }

    /**
     * 清空当前协程的上下文栈
     */
    public static function clear(): void
    {
        self::setStack([]);
    }
}

