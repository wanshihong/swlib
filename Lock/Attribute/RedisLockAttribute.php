<?php
declare(strict_types=1);

namespace Swlib\Lock\Attribute;

use Attribute;
use Swlib\Lock\RedisLock;
use Swlib\Lock\Trait\LockAttributeTrait;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Throwable;

#[Attribute(Attribute::TARGET_METHOD)]
class RedisLockAttribute implements ProxyAttributeInterface
{
    use LockAttributeTrait;

    /**
     * Redis分布式锁注解
     *
     * 使用示例:
     * ```php
     * #[RedisLockAttribute(keyTemplate: 'userId', ttl: 5000)]
     * public static function someMethod(int $userId, string $data): void
     * ```
     *
     * @param string|null $keyTemplate 锁的参数名（直接写方法参数名，如 'userId'）
     *                                 - null: 使用所有参数序列化后的hash作为锁key（默认）
     *                                 - 'userId': 使用$userId参数值，生成的key为 "类名::方法名:123"
     * @param int $ttl 锁超时时间（毫秒），默认10000ms
     * @param int $retryCount 获取锁失败时的重试次数，默认3次
     * @param int $retryDelay 重试间隔（毫秒），默认200ms
     * @param int $priority 代理优先级
     * @param bool $async 是否异步执行
     */
    public function __construct(
        private readonly ?string $keyTemplate = null,
        private readonly int     $ttl = 10000,
        private readonly int     $retryCount = 3,
        private readonly int     $retryDelay = 200,
        public int               $priority = 0,
        public bool              $async = false
    )
    {

    }

    /**
     * @throws Throwable
     */
    public function handle(array $ctx, callable $next): mixed
    {
        $key = $this->buildKey($ctx);

        return RedisLock::withLock(
            $key,
            static fn() => $next($ctx),
            $this->ttl,
            $this->retryCount,
            $this->retryDelay
        );
    }


}

