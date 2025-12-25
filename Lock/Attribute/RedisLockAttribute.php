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

