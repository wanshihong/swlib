<?php

declare(strict_types=1);

namespace Swlib\Table\Trait;

use Exception;
use mysqli;
use Swlib\Connect\PoolMysql;
use Swlib\Enum\CtxEnum;
use Swlib\Event\EventEnum;
use Swlib\Table\Event\DatabaseTransactionEvent;
use Swlib\Table\Helper\InnodbLockWaitTimeout;
use Swoole\Database\MysqliProxy;
use Throwable;

/**
 * 数据库事务封装 Trait
 *
 * 将 Db::transaction 的实现拆分到独立的 TransactionTrait 中，便于复用和维护，
 * 也方便通过事件(AOP)统一接入事务日志和监控。
 */
trait TransactionTrait
{

    // 在 Db 类中定义事务隔离级别常量
    const int ISOLATION_READ_UNCOMMITTED = 1;
    const int ISOLATION_READ_COMMITTED = 2;
    const int ISOLATION_REPEATABLE_READ = 3;
    const int ISOLATION_SERIALIZABLE = 4;

    /**
     * 执行数据库事务
     *
     * @param callable $call 事务中要执行的回调函数，参数为当前事务使用的 mysqli 连接
     * @param string $dbName 数据库名称（支持使用 default 等别名）
     * @param int|null $isolationLevel 事务隔离级别（使用 Db::ISOLATION_* 常量），null 表示使用默认隔离级别
     * @param int|null $timeout 事务锁等待超时时间（秒），null 表示不修改 innodb_lock_wait_timeout
     * @param bool $enableLog 是否记录事务日志（通过事件监听器 DatabaseTransactionLogEvent 实现）
     * @return mixed   回调函数的返回值
     * @throws Throwable
     */
    public static function transaction(
        callable $call,
        string   $dbName = 'default',
        ?int     $isolationLevel = null,
        ?int     $timeout = null,
        bool     $enableLog = false
    ): mixed
    {
        // 解析 default 等别名，得到真实的数据库名称
        $resolvedDbName = PoolMysql::getDbName($dbName);

        // 如果已经在事务中，则不再开启新的事务，直接复用当前事务连接
        if ($existingDbh = CtxEnum::TransactionDbh->get()) {
            $existingDbName = CtxEnum::TransactionDbName->get($resolvedDbName);
            if ($existingDbName !== $resolvedDbName) {
                throw new Exception("事务内部不能跨数据库操作，当前事务数据库为 {$existingDbName}，本次请求的数据库为 $resolvedDbName");
            }

            return call_user_func($call, $existingDbh);
        }

        $startTime = microtime(true);

        return PoolMysql::call(function (MysqliProxy|mysqli $dbh) use ($call, $isolationLevel, $timeout, $enableLog, $startTime, $dbName, $resolvedDbName) {
            // 记录当前事务使用的连接到协程上下文，便于后续查询复用
            CtxEnum::TransactionDbh->set($dbh);
            CtxEnum::TransactionDbName->set($resolvedDbName);
            CtxEnum::EnableTransactionLog->set($enableLog);

            // 如果未显式指定隔离级别，则使用默认隔离级别（与 MySQL 默认 REPEATABLE READ 保持一致）
            if ($isolationLevel === null) {
                $isolationLevel = self::ISOLATION_REPEATABLE_READ;
            }

            $timeoutHelper = new InnodbLockWaitTimeout($timeout, $dbh);

            // 仅在显式指定 timeout 时，才读取并修改 innodb_lock_wait_timeout
            if ($timeout !== null) {
                try {
                    $timeoutHelper->set();
                } catch (Throwable $e) {
                    CtxEnum::TransactionDbh->del();
                    CtxEnum::TransactionDbName->del();
                    self::emitTransactionEvent(
                        $resolvedDbName,
                        'begin_error',
                        $startTime,
                        microtime(true),
                        $isolationLevel,
                        $timeout,
                        $e->getMessage(),
                        $enableLog
                    );
                    throw $e;
                }
            }

            // 设置事务隔离级别（如果指定）
            try {
                $dbh->query("SET TRANSACTION ISOLATION LEVEL " . self::getIsolationLevelName($isolationLevel));
            } catch (Throwable $e) {
                CtxEnum::TransactionDbh->del();
                CtxEnum::TransactionDbName->del();
                self::emitTransactionEvent(
                    $resolvedDbName,
                    'begin_error',
                    $startTime,
                    microtime(true),
                    $isolationLevel,
                    $timeout,
                    $e->getMessage(),
                    $enableLog
                );
                throw $e;
            }

            // 发出“事务开始”事件（便于监控/统计）
            self::emitTransactionEvent(
                $resolvedDbName,
                'begin',
                $startTime,
                microtime(true),
                $isolationLevel,
                $timeout,
                null,
                $enableLog
            );

            // 正式开启事务
            try {
                $dbh->begin_transaction();
            } catch (Throwable $e) {
                CtxEnum::TransactionDbh->del();
                CtxEnum::TransactionDbName->del();
                self::emitTransactionEvent(
                    $resolvedDbName,
                    'begin_error',
                    $startTime,
                    microtime(true),
                    $isolationLevel,
                    $timeout,
                    $e->getMessage(),
                    $enableLog
                );
                throw $e;
            }

            try {
                // 业务代码执行
                $res = call_user_func($call, $dbh);
                $dbh->commit();

                self::emitTransactionEvent(
                    $resolvedDbName,
                    'commit',
                    $startTime,
                    microtime(true),
                    $isolationLevel,
                    $timeout,
                    null,
                    $enableLog
                );

                return $res;
            } catch (Throwable $e) {
                // 提交失败/业务异常时尝试回滚
                try {
                    $dbh->rollback();
                    self::emitTransactionEvent(
                        $resolvedDbName,
                        'rollback',
                        $startTime,
                        microtime(true),
                        $isolationLevel,
                        $timeout,
                        $e->getMessage(),
                        $enableLog
                    );
                } catch (Throwable $rollbackError) {
                    // 回滚本身失败，需要单独记录
                    self::emitTransactionEvent(
                        $resolvedDbName,
                        'rollback_error',
                        $startTime,
                        microtime(true),
                        $isolationLevel,
                        $timeout,
                        $rollbackError->getMessage(),
                        $enableLog
                    );
                }

                throw $e;
            } finally {
                // 恢复原始 innodb_lock_wait_timeout，避免影响后续使用该连接的请求
                if ($timeout !== null) {
                    try {
                        $timeoutHelper->restore();
                    } catch (Throwable $restoreError) {
                        self::emitTransactionEvent(
                            $resolvedDbName,
                            'restore_timeout_error',
                            $startTime,
                            microtime(true),
                            $isolationLevel,
                            $timeout,
                            $restoreError->getMessage(),
                            $enableLog
                        );
                    }
                }

                CtxEnum::TransactionDbh->del();
                CtxEnum::TransactionDbName->del();
            }
        }, $dbName);
    }


    /**
     * 获取事务隔离级别名称
     *
     * @throws Exception
     */
    private static function getIsolationLevelName(int $level): string
    {
        return match ($level) {
            self::ISOLATION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
            self::ISOLATION_READ_COMMITTED => 'READ COMMITTED',
            self::ISOLATION_REPEATABLE_READ => 'REPEATABLE READ',
            self::ISOLATION_SERIALIZABLE => 'SERIALIZABLE',
            default => throw new Exception('Invalid isolation level'),
        };
    }

    /**
     * 触发数据库事务事件，供日志/监控等统一消费
     */
    private static function emitTransactionEvent(
        string  $database,
        string  $stage,
        float   $startTime,
        float   $now,
        ?int    $isolationLevel,
        ?int    $timeout,
        ?string $error,
        bool    $enableLog
    ): void
    {
        $duration = round(($now - $startTime) * 1000, 2);

        $event = new DatabaseTransactionEvent(
            database: $database,
            stage: $stage,
            startTime: $startTime,
            time: $now,
            duration: $duration,
            isolationLevel: $isolationLevel,
            timeout: $timeout,
            error: $error,
            logTransaction: $enableLog,
        );

        EventEnum::DatabaseTransactionEvent->emit(['event' => $event]);
    }
}

