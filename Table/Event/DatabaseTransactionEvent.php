<?php

namespace Swlib\Table\Event;

/**
 * 数据库事务事件
 *
 * 用于在事务生命周期（开始 / 提交 / 回滚等）过程中做统一的日志和监控
 */
class DatabaseTransactionEvent
{
    public function __construct(
        public string $database,           // 数据库名称
        public string $stage,              // 阶段: begin/commit/rollback/rollback_error/restore_timeout_error 等
        public float  $startTime,          // 事务开始时间戳（秒）
        public float  $time,               // 当前阶段时间戳（秒）
        public float  $duration,           // 从开始到当前阶段的耗时（毫秒）
        public ?int   $isolationLevel = null, // 事务隔离级别
        public ?int   $timeout = null,         // innodb_lock_wait_timeout
        public ?string $error = null,          // 错误信息（如果有）
        public bool   $logTransaction = false, // 是否需要记录事务日志（保持与原有 logTransaction 参数语义一致）
    ) {
    }
}

