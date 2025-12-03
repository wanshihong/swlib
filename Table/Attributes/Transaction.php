<?php
declare(strict_types=1);

namespace Swlib\Table\Attributes;

use Attribute;

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
class Transaction
{
    public string $dbName;
    public ?int $isolationLevel;
    public ?int $timeout;
    public bool $logTransaction;

    public function __construct(
        string $dbName = 'default',
        ?int $isolationLevel = null,
        ?int $timeout = null,
        bool $logTransaction = false
    ) {
        $this->dbName = $dbName;
        $this->isolationLevel = $isolationLevel;
        $this->timeout = $timeout;
        $this->logTransaction = $logTransaction;
    }
}

