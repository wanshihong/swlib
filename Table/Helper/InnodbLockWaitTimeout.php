<?php

namespace Swlib\Table\Helper;

use mysqli;
use mysqli_result;
use Swoole\Database\MysqliProxy;
use Throwable;

/**
 * 设置 innodb_lock_wait_timeout 的小工具类
 *
 * 负责：
 * - 在设置事务锁等待超时时间之前记录原始值
 * - 在事务结束后恢复原始值，避免泄漏到连接生命周期
 *
 * 事件记录、事务上下文等逻辑由调用方（例如 TransactionTrait）负责
 */
class InnodbLockWaitTimeout
{
    private ?int $originalTimeout = null;
    private ?int $timeout;
    private MysqliProxy|mysqli $dbh;

    public function __construct(?int $timeout, MysqliProxy|mysqli $dbh)
    {
        $this->dbh = $dbh;
        $this->timeout = $timeout;
    }

    /**
     * 设置 innodb_lock_wait_timeout 并记录原始值
     *
     * @throws Throwable
     */
    public function set(): void
    {
        if ($this->timeout === null) {
            return;
        }

        // 记录原始 innodb_lock_wait_timeout，事务结束后需要恢复，避免泄漏到连接生命周期
        try {
            $result = $this->dbh->query("SELECT @@SESSION.innodb_lock_wait_timeout AS timeout");
            if ($result instanceof mysqli_result) {
                $row = $result->fetch_assoc();
                if ($row && isset($row['timeout'])) {
                    $this->originalTimeout = (int)$row['timeout'];
                }
                $result->close();
            }
        } catch (Throwable) {
            // 读取失败直接忽略，保持兼容旧行为
        }

        // 设置事务锁等待超时时间
        $timeout = (int)$this->timeout;
        $this->dbh->query("SET innodb_lock_wait_timeout = $timeout");
    }

    /**
     * 恢复原始 innodb_lock_wait_timeout（如果有记录）
     *
     * @throws Throwable
     */
    public function restore(): void
    {
        if ($this->originalTimeout === null) {
            return;
        }

        $timeout = $this->originalTimeout;
        $this->dbh->query("SET innodb_lock_wait_timeout = $timeout");
    }
}