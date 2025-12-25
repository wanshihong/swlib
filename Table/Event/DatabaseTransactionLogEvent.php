<?php

namespace Swlib\Table\Event;

use Swlib\Event\Abstract\AbstractEvent;
use Swlib\Event\Attribute\Event;
use Swlib\Event\EventEnum;
use Swlib\Utils\Log;

/**
 * 数据库事务日志事件监听器
 *
 * 负责根据事务生命周期阶段（stage）统一记录事务相关日志：
 * - begin                ：事务开始
 * - begin_error          ：事务开始前（设置隔离级别 / 超时时间等）发生错误
 * - commit               ：事务提交成功
 * - rollback             ：事务回滚（业务执行失败）
 * - rollback_error       ：事务回滚本身失败
 * - restore_timeout_error：事务结束后恢复 innodb_lock_wait_timeout 失败
 */
#[Event(EventEnum::DatabaseTransactionEvent->name)]
class DatabaseTransactionLogEvent extends AbstractEvent
{
    public function handle(array $args): void
    {
        /** @var DatabaseTransactionEvent|null $event */
        $event = $args['event'] ?? null;
        if (!$event instanceof DatabaseTransactionEvent) {
            return;
        }

        // 未开启事务日志时直接返回（保持与 logTransaction 参数语义一致）
        if (!$event->logTransaction) {
            return;
        }

        $db = $event->database;
        $duration = $event->duration;

        switch ($event->stage) {
            case 'begin':
                Log::save("开始事务 [数据库: $db]", 'transaction');
                break;

            case 'begin_error':
                Log::save("事务开始失败 [数据库: $db, 错误: $event->error]", 'transaction');
                break;

            case 'commit':
                Log::save("事务提交成功 [数据库: $db, 耗时: {$duration}ms]", 'transaction');
                break;

            case 'rollback':
                Log::save("事务回滚 [数据库: $db, 耗时: {$duration}ms, 错误: $event->error]", 'transaction');
                break;

            case 'rollback_error':
                Log::save("事务回滚失败 [数据库: $db, 错误: $event->error]", 'transaction');
                break;

            case 'restore_timeout_error':
                Log::save("事务结束后恢复 innodb_lock_wait_timeout 失败 [数据库: $db, 错误: $event->error]", 'transaction');
                break;
        }
    }
}

