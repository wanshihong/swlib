<?php
declare(strict_types=1);

namespace Swlib\Table;

use DateInterval;
use DateTime;
use Exception;
use Generate\ConfigEnum;
use Generate\TableFieldMap;
use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Func;
use Swlib\Utils\Log;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use Redis;
use ReflectionClass;
use ReflectionException;
use Swoole\Database\MysqliProxy;
use Swoole\Database\MysqliStatementProxy;
use Throwable;


class Db
{

    const string ACTION_GET_RESULT = 'get-result';
    const string ACTION_EXEC = 'exec';
    const string ACTION_UPDATE = 'update';
    const string ACTION_DELETE = 'delete';
    const string ACTION_INSERT = 'insert';
    const string ACTION_INSERT_ALL = 'insert-all';
    const string ACTION_GET_ITERATOR = 'get-iterator';

    // 在 Db 类中定义事务隔离级别常量
    const int ISOLATION_READ_UNCOMMITTED = 1;
    const int ISOLATION_READ_COMMITTED = 2;
    const int ISOLATION_REPEATABLE_READ = 3;
    const int ISOLATION_SERIALIZABLE = 4;

    private mysqli_result|array|int|null $queryResult = null;// SQL 查询结果
    private MysqliStatementProxy|mysqli_stmt|false $stmt = false;


    public function __construct(
        private readonly string $action,// 当前的查询类型
        private readonly string $sql,
        private readonly array  $bindParams = [],
        private readonly bool   $debugSql = false,
        private readonly string $dbName = "default" // // 使用过的那个数据库连接
    )
    {

    }

    public function close(): void
    {

        // 如果结果没有被释放，则释放
        if ($this->queryResult instanceof mysqli_result) {
            $this->queryResult->close();
        }

        if ($this->stmt) {
            $this->_stmtClose();
        }
    }

    private function _stmtClose(): void
    {
        if ($this->stmt) {
            $this->stmt->free_result();
            $this->stmt->close();
            $this->stmt = false;
        }
    }

    /**
     * 执行数据库事务
     *
     * @param callable $call 事务中要执行的回调函数
     * @param string $dbName 数据库名称
     * @param int|null $isolationLevel 事务隔离级别
     * @param int $timeout 事务超时时间（秒）
     * @param bool $logTransaction 是否记录事务日志
     * @return mixed 回调函数的返回值
     * @throws Throwable
     */
    public static function transaction(
        callable $call,
        string   $dbName = 'default',
        ?int     $isolationLevel = null,
        int      $timeout = 30,
        bool     $logTransaction = false
    ): mixed
    {
        // 检查是否已在事务中
        if ($existingDbh = CtxEnum::TransactionDbh->get()) {
            // 已在事务中，直接使用现有连接执行回调
            return call_user_func($call, $existingDbh);
        }

        $startTime = microtime(true);

        return PoolMysql::call(function (MysqliProxy|mysqli $dbh) use ($call, $isolationLevel, $timeout, $logTransaction, $startTime, $dbName) {
            // 把当前事务使用的连接记录到协程上下文
            CtxEnum::TransactionDbh->set($dbh);

            // 设置事务隔离级别（如果指定）
            if ($isolationLevel !== null) {
                $dbh->query("SET TRANSACTION ISOLATION LEVEL " . self::getIsolationLevelName($isolationLevel));
            }

            // 设置事务超时
            $dbh->query("SET innodb_lock_wait_timeout = $timeout");

            if ($logTransaction) {
                Log::save("开始事务 [数据库: $dbName]", 'transaction');
            }

            $dbh->begin_transaction();
            try {
                $res = call_user_func($call, $dbh);
                $dbh->commit();

                if ($logTransaction) {
                    $duration = round((microtime(true) - $startTime) * 1000, 2);
                    Log::save("事务提交成功 [数据库: $dbName, 耗时: {$duration}ms]", 'transaction');
                }

                return $res;
            } catch (Throwable $e) {
                $dbh->rollBack();

                if ($logTransaction) {
                    $duration = round((microtime(true) - $startTime) * 1000, 2);
                    Log::save("事务回滚 [数据库: $dbName, 耗时: {$duration}ms, 错误: {$e->getMessage()}]", 'transaction');
                }

                throw $e;
            } finally {
                CtxEnum::TransactionDbh->del();
            }
        }, $dbName);
    }

    /**
     * 获取事务隔离级别名称
     */
    private static function getIsolationLevelName(int $level): string
    {
        return match ($level) {
            self::ISOLATION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
            self::ISOLATION_READ_COMMITTED => 'READ COMMITTED',
            self::ISOLATION_REPEATABLE_READ => 'REPEATABLE READ',
            self::ISOLATION_SERIALIZABLE => 'SERIALIZABLE',
            default => 'REPEATABLE READ'
        };
    }

    /**
     * @throws Throwable
     */
    public static function query(string $sql, $bindParams = []): array
    {
        $db = new Db(Db::ACTION_GET_RESULT, $sql, $bindParams);
        return $db->getResult();
    }


    /**
     * @throws Throwable
     */
    public function getCacheResult(int $cacheTime, string $cacheKey = ''): array
    {
        if (empty($cacheKey)) {
            $cacheKey = "sql:" . md5($this->sql . implode('', $this->bindParams));
        }
        $cacheData = PoolRedis::call(function (Redis $redis) use ($cacheKey) {
            if ($redis->exists($cacheKey)) {
                $arr = $redis->get($cacheKey);
                return unserialize($arr);
            }
            return false;
        });
        if ($cacheData !== false) {
            return $cacheData;
        }

        $this->exec();
        $arr = $this->queryResult;
        if (empty($arr)) {
            return [];
        }

        PoolRedis::call(function (Redis $redis) use ($cacheKey, $arr, $cacheTime) {
            $redis->set($cacheKey, serialize($arr));
            $redis->expire($cacheKey, $cacheTime);
        });
        return $arr;
    }

    /**
     * @throws Throwable
     */
    public function getResult(): array
    {
        $this->exec();
        return $this->queryResult;
    }

    /**
     * @throws Throwable
     */
    public function getIterator(): iterable
    {
        $this->exec();
        /**@var mysqli_result $result */
        $result = $this->queryResult;
        return $result->getIterator();
    }

    /**
     * @throws Throwable
     */
    public function getInsertId(): int
    {
        $this->exec();
        return $this->queryResult;
    }

    /**
     * @throws Throwable
     */
    public function getAffectedRows(): int
    {
        $this->exec();
        return $this->queryResult;
    }

    /**
     * @throws Throwable
     */
    private function exec(): void
    {

        if (ConfigEnum::DB_SLOW_TIME > 0) {
            $start = microtime(true);

            $this->_getDbh();

            $executionTime = (microtime(true) - $start) * 1000;

            // 执行时间大于配置时间，就记录慢日志
            if ($executionTime > ConfigEnum::DB_SLOW_TIME) {
                $params = implode(',', $this->bindParams);
                Log::save("$executionTime ms: $this->sql;[$params]", 'sql_slow');
            }

        } else {
            $this->_getDbh();
        }

    }

    /**
     * @throws Throwable
     */
    private function _getDbh(): void
    {
        // 如果有事务，则直接使用事务的连接
        if ($dbh = CtxEnum::TransactionDbh->get()) {
            try {
                $this->_mysqlExecSql($dbh);
            } catch (Throwable $e) {
                // 确保在发生异常时也能关闭连接
                $this->close();
                throw $e;
            }
        } else {
            // 取一个新的数据库连接执行查询
            PoolMysql::call(function (MysqliProxy|mysqli $dbh) {
                try {
                    $this->_mysqlExecSql($dbh);
                } catch (Throwable $e) {
                    // 确保在发生异常时也能关闭连接
                    $this->close();
                    throw $e;
                }
            }, $this->dbName);
        }
    }


    /**
     * @throws Throwable
     */
    private function _mysqlExecSql(MysqliProxy|mysqli $dbh): void
    {
        try {
            // 执行SQL 预处理
            $this->stmt = $dbh->prepare($this->sql);

            // 绑定查询参数
            $this->_bindParams($this->stmt);

            // 执行预处理语句
            $execRes = $this->stmt->execute();


            switch ($this->action) {
                case self::ACTION_GET_RESULT:
                    $result = $this->stmt->get_result();
                    $this->queryResult = $result->fetch_all(MYSQLI_ASSOC);
                    $result->close();
                    $this->_stmtClose();
                    break;
                case self::ACTION_GET_ITERATOR:
                    $this->queryResult = $this->stmt->get_result();
                    break;
                case self::ACTION_INSERT_ALL:
                case self::ACTION_EXEC:
                case self::ACTION_UPDATE:
                case self::ACTION_DELETE:
                    $this->queryResult = $this->stmt->affected_rows;
                    $this->_stmtClose();
                    break;
                case self::ACTION_INSERT:
                    $this->queryResult = $this->stmt->insert_id;
                    $this->_stmtClose();
                    break;
                default:
                    throw new Exception("Unsupported action: " . $this->action);
            }


            // 执行记录SQL
            $this->_saveLogSql($this->debugSql);

            if ($execRes === false) {
                throw new Exception("Execute failed: (" . $this->stmt->errno . ") " . $this->stmt->error);
            }

        } catch (Throwable $e) {
            // 出错了也记录
            $this->_saveLogSql(true);
            throw  $e;
        } finally {
            // 如果是 iterator 查询数据，在迭代完成以后关闭，否则获取不到数据
            if ($this->action !== self::ACTION_GET_ITERATOR) {
                $this->close(); // 确保在任何情况下都能关闭语句句柄
            }
        }
    }


    /**
     * @throws Exception
     */
    private function _bindParams(MysqliStatementProxy $stmt): void
    {
        $typeString = '';
        $params = [];

        foreach ($this->bindParams as $param) {
            switch (true) {
                case is_null($param):
                    $typeString .= 's';
                    $params[] = null;
                    break;
                case is_bool($param):
                    $typeString .= 'i';
                    $params[] = (int)$param;
                    break;
                case is_int($param):
                    $typeString .= 'i';
                    $params[] = $param;
                    break;
                case is_string($param):
                    $typeString .= 's';
                    $params[] = $param;
                    break;
                case is_double($param):
                    $typeString .= 'd';
                    $params[] = $param;
                    break;
                case $param instanceof DateTime:
                    $typeString .= 's';
                    $params[] = $param->format('Y-m-d H:i:s');
                    break;
                case $param instanceof DateInterval:
                    $typeString .= 's';
                    $params[] = $param->format('%R%Y-%M-%D %H:%I:%S');
                    break;
                case is_array($param):
                    $typeString .= 's';
                    $params[] = json_encode($param, JSON_UNESCAPED_UNICODE);
                    break;
                default:
                    throw new Exception("Unsupported parameter type: " . gettype($param) . ' value:' . $param);
            }
        }

        if ($typeString) {
            $stmt->bind_param($typeString, ...$params);
        }
    }

    private function _saveLogSql($save = false): void
    {
        if ($this->sql && $save) {
            $params = '';
            if ($this->bindParams) {
                $params = implode(',', $this->bindParams);
            }
            Log::save($this->sql . "[$params]", 'sql');
        }
    }


    /**
     * 根据数据库字段别名获取字段名称
     * @param string $fieldAs 现在是数据库字段别名
     * @param string $dbName
     * @return string
     * @throws Exception
     */
    public static function getFieldNameByAs(string $fieldAs, string $dbName = 'default'): string
    {
        try {
            // 优先进行查找返回，因为这个频率是最高的
            return self::_getFieldNameByAs($fieldAs, $dbName);
        } catch (Throwable) {
            // 没有找到，证明有特殊的操作

            if (stripos($fieldAs, TableEnum::FUNCTION_FIELD_DELIMITER->value) !== false) {
                // 是否对字段进行 mysql 函数计算
                [$field, $func] = explode(TableEnum::FUNCTION_FIELD_DELIMITER->value, $fieldAs, 2);
                $field = self::_getFieldNameByAs($field, $dbName);
                return "$func($field)";
            }

            if (str_contains($fieldAs, TableEnum::AS_TABLE_DELIMITER->value)) {
                // 如果字段中间有 '_as_' 表示需要替换数据表格名称
                [$tempFieldAs, $tableNameAs] = explode(TableEnum::AS_TABLE_DELIMITER->value, $fieldAs, 2);
                $field = self::_getFieldNameByAs($tempFieldAs, $dbName);
                [, $fieldName] = explode('.', $field, 2);
                return "$tableNameAs.$fieldName";
            }

            throw new Exception("在别名定义中没有找到 $fieldAs");
        }

    }

    /**
     * 根据字段别名获取 数据库字段名
     * @throws Exception
     */
    private static function _getFieldNameByAs(string $fieldAs, string $dbName = 'default'): string
    {
        $dbName = PoolMysql::getDbName($dbName);
        if (!isset(TableFieldMap::maps[$dbName][$fieldAs])) {
            throw new Exception("在字段定义中没有找到$fieldAs");
        }
        return TableFieldMap::maps[$dbName][$fieldAs];
    }


    /**
     * 根据字段名称获取数据库字段别名
     * @throws Exception
     */
    public static function getFieldAsByName(string $fieldName, string $dbName = 'default'): string
    {
        $dbName = PoolMysql::getDbName($dbName);
        $res = array_search($fieldName, TableFieldMap::maps[$dbName]);
        if (empty($res)) {
            throw new Exception("在别名定义中没有找到$fieldName");
        }
        return (string)$res;
    }


    /**
     * 检查字段是否存在
     * @param string $fieldName
     * @param string $dbName
     * @return bool
     */
    public static function checkFieldExists(string $fieldName, string $dbName = 'default'): bool
    {
        return in_array($fieldName, TableFieldMap::maps[$dbName]);
    }

    /**
     * 检查别名是否存在
     * @param string $asName
     * @param string $dbName
     * @return bool
     */
    public static function checkAsExists(string $asName, string $dbName = 'default'): bool
    {
        return isset(TableFieldMap::maps[$dbName][$asName]);
    }


    /**
     * 通过类的反射获取数据库表的反射
     * @throws ReflectionException
     * @throws Exception
     */
    public static function getTableReflection(string $className): ReflectionClass
    {
        $dbName = PoolMysql::getDbName();
        $dbName = Func::underscoreToCamelCase($dbName);
        $routerTableName = "Generate\\Tables\\$dbName\\$className";

        // 使用 ReflectionClass 动态导入类
        return new ReflectionClass($routerTableName);
    }

    /**
     * 生成 update 增量字段 sql
     * 用户也可以手动拼接，只是调用函数减少出错概率
     * @param string $field 需要增量的字段
     * @param int $value 需要增量的 值
     * @param string $operator 运算符符号  +  - *  /
     *
     * 示例 ：
     * new Table()->where([
     *     Table::ID => 25
     * ])->update([
     *     Table::TIME => Db::incr(Table::TIME, 1)
     * ]);
     *
     * @return string
     */
    public static function incr(string $field, int $value = 1, string $operator = '+'): string
    {
        return "$field$operator$value";
    }

}

