<?php

namespace Swlib\Table\Event;

use Generate\ConfigEnum;
use Swlib\Event\Abstract\AbstractEvent;
use Swlib\Event\Attribute\Event;
use Swlib\Event\EventEnum;
use Swlib\Table\Operation\DatabaseOperationContext;
use Swlib\Utils\Log;

/**
 * 统一处理 SQL 日志和慢查询日志
 *
 * - 慢查询：executionTime > ConfigEnum::DB_SLOW_TIME 时记录到 sql_slow 日志
 * - 普通 SQL：当 debugSql=true 或 ConfigEnum::DB_SAVE_SQL=true 时记录到 sql 日志
 * - 异常 SQL：无论是否开启 debugSql，都记录到 sql 日志
 */
#[Event(EventEnum::DatabaseAfterExecuteEvent->name)]
class DatabaseSqlLogEvent extends AbstractEvent
{
    public function handle(array $args): void
    {
        /** @var DatabaseOperationContext|null $event */
        $event = $args['event'] ?? null;
        if (!$event instanceof DatabaseOperationContext) {
            return;
        }

        // 慢查询日志
        if (ConfigEnum::DB_SLOW_TIME > 0 && $event->executionTime > ConfigEnum::DB_SLOW_TIME) {
            $params = $this->formatParams($event->bindParams);
            Log::save("$event->executionTime ms: $event->sql[$params]", 'sql_slow');
        }

        // 普通 SQL 日志：调试或全局开启时记录
        $shouldLogSql = $event->debugSql || ConfigEnum::DB_SAVE_SQL;
        if ($shouldLogSql) {
            $params = $this->formatParams($event->bindParams);
            Log::save($event->sql . "[$params]", 'sql');
        }

        // 异常 SQL：始终记录
        if ($event->error !== null) {
            $params = $this->formatParams($event->bindParams);
            Log::save("SQL 执行异常: $event->error | $event->sql[$params]", 'sql');
        }
    }

    private function formatParams(array $params): string
    {
        if (empty($params)) {
            return '';
        }
        return implode(',', $params);
    }
}

