<?php

declare(strict_types=1);

namespace Swlib\Coroutine;

use Swlib\Enum\CtxEnum;
use Swoole\Coroutine;

/**
 * 通用协程上下文管理器
 * 负责协程上下文的捕获、传递和恢复
 *
 * 使用场景：
 * - 事件系统的异步/队列/延迟执行
 * - 任何需要在新协程中保持父协程上下文的场景
 * - 替代直接使用 Coroutine::create() 以确保上下文传递
 */
class CoroutineContext
{
    /**
     * 需要排除的上下文键（不应跨协程传递）
     * 这些键通常与当前协程的资源绑定，不能跨协程使用
     */
    private const array EXCLUDED_KEYS = [
        CtxEnum::TransactionDbh->value,      // 事务数据库连接（事务不可跨协程）
        CtxEnum::TransactionDbName->value,   // 事务数据库名
    ];

    /**
     * 捕获当前协程上下文
     * 用于在新协程中恢复上下文
     *
     * @param array $excludeKeys 额外需要排除的键
     * @return array 上下文数据
     */
    public static function capture(array $excludeKeys = []): array
    {
        $context = [];
        $currentContext = Coroutine::getContext();

        if ($currentContext === null) {
            return $context;
        }

        $allExcluded = array_merge(self::EXCLUDED_KEYS, $excludeKeys);

        foreach ($currentContext as $key => $value) {
            if (in_array($key, $allExcluded, true)) {
                continue;
            }
            $context[$key] = $value;
        }

        return $context;
    }

    /**
     * 恢复协程上下文
     * 将捕获的上下文数据恢复到当前协程
     *
     * @param array $parentContext 父协程上下文
     * @param bool $overwrite 是否覆盖已存在的键，默认 false
     * @return void
     */
    public static function restore(array $parentContext, bool $overwrite = false): void
    {
        $newContext = Coroutine::getContext();

        if ($newContext === null) {
            return;
        }

        foreach ($parentContext as $key => $value) {
            if ($overwrite || !isset($newContext[$key])) {
                $newContext[$key] = $value;
            }
        }
    }

    /**
     * 在新协程中执行回调，并自动传递上下文
     *
     * @param callable $callback 要执行的回调
     * @param array $excludeKeys 额外需要排除的键
     * @return int 新协程的 ID
     */
    public static function create(callable $callback, array $excludeKeys = []): int
    {
        $parentContext = self::capture($excludeKeys);

        return Coroutine::create(function () use ($callback, $parentContext) {
            self::restore($parentContext);
            return $callback();
        });
    }

    /**
     * 检查当前是否在协程环境中
     *
     * @return bool
     */
    public static function inCoroutine(): bool
    {
        return Coroutine::getCid() > 0;
    }

    /**
     * 获取当前协程 ID
     *
     * @return int
     */
    public static function getCid(): int
    {
        return Coroutine::getCid();
    }

    /**
     * 获取父协程 ID
     *
     * @param int|null $cid 协程 ID，默认当前协程
     * @return int|false 父协程 ID，顶级协程返回 -1，非协程环境返回 false
     */
    public static function getParentCid(?int $cid = null): int|false
    {
        return Coroutine::getPcid($cid);
    }

    /**
     * 获取默认排除的键列表
     *
     * @return array
     */
    public static function getExcludedKeys(): array
    {
        return self::EXCLUDED_KEYS;
    }
}
