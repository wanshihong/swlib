<?php
declare(strict_types=1);

namespace Swlib\Lock\Attribute;

use Attribute;
use Swlib\Lock\Trait\LockAttributeTrait;
use Swlib\Lock\WorkerLock;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Throwable;

#[Attribute(Attribute::TARGET_METHOD)]
class WorkerLockAttribute implements ProxyAttributeInterface
{
    use LockAttributeTrait;

    public function __construct(
        private readonly ?string $keyTemplate = null,
        private readonly int     $timeout = 3,
        private readonly int     $ttl = 60,
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

        return WorkerLock::withLock(
            $key,
            static fn() => $next($ctx),
            $this->timeout,
            $this->ttl,
            $this->retryCount,
            $this->retryDelay
        );
    }

}

