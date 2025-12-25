<?php
declare(strict_types=1);

namespace Swlib\Table\Attributes;

use Attribute;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Table\Db;
use Throwable;

/**
 * 事务注解
 *
 * 用于在方法上声明需要开启数据库事务。
 *
 * 使用示例：
 * #[Transaction]
 * #[Transaction(dbName: 'default')]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Transaction implements ProxyAttributeInterface
{


    public function __construct(
        public string $dbName = 'default',
        public ?int   $isolationLevel = null,
        public ?int   $timeout = null,
        public bool   $logTransaction = false,
        public int    $priority = 0,
        public bool   $async = false
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(array $ctx, callable $next): mixed
    {
        return Db::transaction(
            call: static fn() => $next($ctx),
            dbName: $this->dbName,
            isolationLevel: $this->isolationLevel,
            timeout: $this->timeout,
            enableLog: $this->logTransaction
        );
    }
}

