<?php
declare(strict_types=1);

namespace Swlib\Table\Aspects;

use Attribute;
use Exception;
use Generate\ConfigEnum;
use Swlib\Aop\Abstract\AbstractAspect, Swlib\Aop\JoinPoint;
use Swlib\Connect\PoolMysql;
use Swlib\Enum\CtxEnum, Swlib\Event\EventEnum;
use Swlib\Table\Db;
use Swlib\Table\Event\DatabaseOperationEnum, Swlib\Table\Event\DatabaseOperationEvent;
use Swlib\Table\Interface\TableInterface;
use Throwable;

/**
 * 数据库操作事件 切片
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DatabaseOperationEventAspect extends AbstractAspect
{

    public ?DatabaseOperationEvent $databaseOperation = null;

    /**
     * 前置通知 - 记录方法调用
     *
     * @param JoinPoint $joinPoint
     * @return void
     * @throws Exception
     */
    public function before(JoinPoint $joinPoint): void
    {
        $target = $joinPoint->target;
        $className = $joinPoint->getTargetClass();

        // Table 查询/写入：保持原有逻辑
        if ($target instanceof TableInterface) {
            // 是否开启了事务日志
            if (CtxEnum::EnableTransactionLog->get()) {
                // 开启了事务日志，就开启SQL 记录
                $target->setDebugSql();
            }

            // 根据调用的方法名推断数据库操作类型
            $operation = match ($joinPoint->methodName) {
                'insertAll', 'insert' => DatabaseOperationEnum::INSERT,
                'delete' => DatabaseOperationEnum::DELETE,
                'update' => DatabaseOperationEnum::UPDATE,
                default => DatabaseOperationEnum::SELECT,
            };

            $this->databaseOperation = new DatabaseOperationEvent(
                database: $target::DATABASES,
                tableName: $target::TABLE_NAME,
                operation: $operation,
                startTime: microtime(true),
                debugSql: $target->debugSql,
            );

            if (in_array($joinPoint->methodName, ['insertAll', 'insert', 'update'], true)) {
                $this->databaseOperation->writeData = $joinPoint->arguments;
                $insertRow = $joinPoint->methodName === 'insertAll' ? $joinPoint->arguments[0] : $joinPoint->arguments;
                $this->databaseOperation->affectedFields = array_keys($insertRow);
            }

            EventEnum::DatabaseBeforeExecuteEvent->emit(['event' => $this->databaseOperation]);
            return;
        }

        // Db::query 的原生 SQL 事件处理
        if ($className === Db::class && $joinPoint->methodName === 'query') {
            $sql = $joinPoint->arguments[0] ?? '';
            $bindParams = $joinPoint->arguments[1] ?? [];
            $bindParams = is_array($bindParams) ? $bindParams : [$bindParams];

            $database = PoolMysql::getDbName();

            $this->databaseOperation = new DatabaseOperationEvent(
                database: $database,
                tableName: '',
                operation: DatabaseOperationEnum::SELECT,
                sql: $sql,
                bindParams: $bindParams,
                startTime: microtime(true),
                debugSql: ConfigEnum::DB_SAVE_SQL,
            );

            EventEnum::DatabaseBeforeExecuteEvent->emit(['event' => $this->databaseOperation]);
        }
    }

    /**
     * 后置通知 - 记录返回值
     *
     * @param JoinPoint $joinPoint
     * @param mixed $result
     * @return void
     */
    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        $target = $joinPoint->target;

        $this->databaseOperation->executionTime = (microtime(true) - $this->databaseOperation->startTime) * 1000;
        $this->databaseOperation->result = $result;

        // Table 查询/写入：使用 QueryBuild 中构建好的 sqlInfo 补全结构化信息
        if ($target instanceof TableInterface) {
            $queryBuild = $target->queryBuild;

            $this->databaseOperation->sql = $queryBuild->sql;
            $this->databaseOperation->bindParams = $queryBuild->bindParams;
            $this->databaseOperation->whereConditions = $queryBuild->whereArray;

            if (in_array($joinPoint->methodName, [
                'insertAll',
                'update',
                'delete',
            ], true)) {
                $this->databaseOperation->affectedRows = is_int($result) ? $result : null;
            }

            if ($joinPoint->methodName === 'insert') {
                $this->databaseOperation->insertId = is_int($result) ? $result : null;
            }
        }

        EventEnum::DatabaseAfterExecuteEvent->emit(['event' => $this->databaseOperation]);
    }

    /**
     * 异常通知 - 记录异常
     *
     * @param JoinPoint $joinPoint
     * @param Throwable $exception
     * @return void
     */
    public function afterThrowing(JoinPoint $joinPoint, Throwable $exception): void
    {
        // 异常也发送执行后事件，方便统一记录错误/慢 SQL
        $this->databaseOperation->executionTime = (microtime(true) - $this->databaseOperation->startTime) * 1000;
        $this->databaseOperation->error = $exception->getMessage() . PHP_EOL . $exception->getTraceAsString();
        EventEnum::DatabaseAfterExecuteEvent->emit(['event' => $this->databaseOperation]);
    }


}

