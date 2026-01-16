<?php
declare(strict_types=1);

namespace Swlib\Table\Aspects;

use Attribute;
use Exception;
use Generate\ConfigEnum;
use Generate\DatabaseConnect;
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;
use Swlib\Enum\CtxEnum;
use Swlib\Event\Attribute\Event;
use Swlib\Event\EventEnum;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Table\Db;
use Swlib\Table\Interface\TableInterface;
use Swlib\Table\Operation\DatabaseOperationContext;
use Swlib\Table\Operation\DatabaseOperationEnum;
use Throwable;

/**
 * 数据库操作事件 切片
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DatabaseOperationEventAspect extends AbstractAspect implements ProxyAttributeInterface
{


    public function __construct(
        public int  $priority = 0,
        public bool $async = false
    )
    {
    }

    public ?DatabaseOperationContext $databaseOperation = null;

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

            $this->databaseOperation = new DatabaseOperationContext(
                database: $target::DATABASES,
                tableName: $target::TABLE_NAME,
                operation: $operation,
                startTime: microtime(true),
                debugSql: $target->debugSql,
            );

            // 记录本次写入的完整数据，以及受影响的字段列表
            if (in_array($joinPoint->methodName, ['insertAll', 'insert', 'update'], true)) {
                // 所有这三种方法的第一个参数，都是用于写入/更新的数据数组
                $firstArg = $joinPoint->arguments[0] ?? [];

                if ($joinPoint->methodName === 'insertAll') {
                    // insertAll: 传入的是二维数组，每个元素是一行
                    $rows = is_array($firstArg) ? $firstArg : [];
                    $this->databaseOperation->writeData = $rows; // 完整的批量写入数据

                    // 使用首行的数据字段，作为本次操作的字段列表
                    $firstRow = (isset($rows[0]) && is_array($rows[0])) ? $rows[0] : [];
                    $this->databaseOperation->affectedFields = array_keys($firstRow);
                } else {
                    // insert / update: 传入的是一维数组，键为字段名，值为写入值
                    $data = is_array($firstArg) ? $firstArg : [];
                    $this->databaseOperation->writeData = $data; // 本次写入/更新的完整数据
                    $this->databaseOperation->affectedFields = array_keys($data);
                }
            }

            // 1) 触发表级事件（按表 + 操作 + 阶段）
            if (method_exists($target, 'getOperationEventName')) {
                $eventName = $target::getOperationEventName($operation, true);
                if ($eventName) {
                    Event::emit($eventName, ['context' => $this->databaseOperation], $this->async);
                }
            }

            // 2) 保持原有全局事件
            EventEnum::DatabaseBeforeExecuteEvent->emit(['context' => $this->databaseOperation]);
            return;
        }

        // Db::query 的原生 SQL 事件处理
        if ($className === Db::class && $joinPoint->methodName === 'query') {
            $sql = $joinPoint->arguments[0] ?? '';
            $bindParams = $joinPoint->arguments[1] ?? [];
            $bindParams = is_array($bindParams) ? $bindParams : [$bindParams];

            $database = DatabaseConnect::getDbName();

            $this->databaseOperation = new DatabaseOperationContext(
                database: $database,
                tableName: '',
                operation: DatabaseOperationEnum::SELECT,
                sql: $sql,
                bindParams: $bindParams,
                startTime: microtime(true),
                debugSql: ConfigEnum::DB_SAVE_SQL,
            );

            EventEnum::DatabaseBeforeExecuteEvent->emit(['context' => $this->databaseOperation]);
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

            // 1) 触发表级事件（按表 + 操作 + 阶段）
            if (method_exists($target, 'getOperationEventName')) {
                $eventName = $target::getOperationEventName($this->databaseOperation->operation, false);
                if ($eventName) {
                    Event::emit($eventName, ['context' => $this->databaseOperation], $this->async);
                }
            }
        }

        // 2) 保持原有全局事件
        EventEnum::DatabaseAfterExecuteEvent->emit(['context' => $this->databaseOperation]);
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
        EventEnum::DatabaseAfterExecuteEvent->emit(['context' => $this->databaseOperation]);
    }


    public function handle(array $ctx, callable $next): mixed
    {
        $joinPoint = new JoinPoint($ctx['target'], $ctx['meta']['method'], $ctx['arguments']);
        return $this->around($joinPoint, static fn() => $next($ctx));
    }
}

