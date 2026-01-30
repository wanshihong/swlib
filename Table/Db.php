<?php
declare(strict_types=1);

namespace Swlib\Table;

use DateInterval;
use DateTime;
use Exception;
use Generate\ConfigEnum;
use Generate\DatabaseConnect;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use Redis;
use Swlib\Connect\PoolRedis;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Table\Aspects\DatabaseOperationEventAspect;
use Swlib\Table\Trait\DbHelperTrait;
use Swlib\Table\Trait\TransactionTrait;
use Swoole\Database\MysqliProxy;
use Swoole\Database\MysqliStatementProxy;
use Throwable;


class Db
{
    use TransactionTrait;
    use DbHelperTrait;

    const string ACTION_GET_RESULT = 'get-result';
    const string ACTION_EXEC = 'exec';
    const string ACTION_UPDATE = 'update';
    const string ACTION_DELETE = 'delete';
    const string ACTION_INSERT = 'insert';
    const string ACTION_INSERT_ALL = 'insert-all';
    const string ACTION_GET_ITERATOR = 'get-iterator';


    private mysqli_result|array|int|null $queryResult = null;// SQL 查询结果
    private MysqliStatementProxy|mysqli_stmt|false $stmt = false;
    private MysqliProxy|mysqli|null $dbh = null;


    public function __construct(
        private readonly string $action,// 当前的查询类型
        private readonly string $sql,
        private readonly array  $bindParams = [],
        private readonly bool   $debugSql = false,
        private readonly string $dbName = "default"  // 使用过的那个数据库连接
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

        // 如果我们为迭代器持有了连接，现在就归还它
        if ($this->dbh !== null) {
            DatabaseConnect::put($this->dbh, $this->dbName);
            $this->dbh = null;
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
     * @throws Throwable
     */
    #[DatabaseOperationEventAspect]
    public static function query(string $sql, $bindParams = []): array
    {
        $db = new Db(Db::ACTION_GET_RESULT, $sql, is_array($bindParams) ? $bindParams : [$bindParams]);
        return $db->getResult();
    }


    /**
     * @throws Throwable
     */
    public function getCacheResult(int $cacheTime, string $cacheKey = ''): array
    {
        if ($cacheTime >= 0) {
            if (empty($cacheKey)) {
                $content = $this->sql . '|' . serialize($this->bindParams) . '|' . $this->dbName;
                $cacheKey = "sql:" . crc32($content) . '_' . substr(md5($content), 0, 16);
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
        }


        $this->exec();
        $arr = $this->queryResult;
        if (empty($arr)) {
            return [];
        }

        if ($cacheTime >= 0) {
            PoolRedis::call(function (Redis $redis) use ($cacheKey, $arr, $cacheTime) {
                $redis->set($cacheKey, serialize($arr));
                $redis->expire($cacheKey, $cacheTime);
            });
        }

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
        try {
            foreach ($result->getIterator() as $row) {
                yield $row;
            }
        } finally {
            $this->close(); // 生成器结束或销毁时，自动关闭资源并归还连接
        }
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
        // 统一交给上层事件/切面处理慢 SQL 和 SQL 日志，这里只负责真正执行
        $this->_getDbh();
    }

    /**
     * @throws Throwable
     */
    private function _getDbh(): void
    {
        // 如果有事务，则直接使用事务的连接，并校验是否跨库
        if ($dbh = CtxEnum::TransactionDbh->get()) {
            $transactionDbName = CtxEnum::TransactionDbName->get();
            if ($transactionDbName !== null) {
                // 当前查询使用的数据库名称（处理 default 别名）
                $currentDbName = DatabaseConnect::getDbName($this->dbName);
                if ($transactionDbName !== $currentDbName) {
                    throw new AppException(AppErr::DB_TRANSACTION_CROSS_DB . ": 事务数据库为 {$transactionDbName}，本次查询的数据库为 $currentDbName");
                }
            }

            $this->executeWithDbh($dbh);
            return;
        }

        // 迭代器查询需要持有连接，直到迭代结束
        if ($this->action === self::ACTION_GET_ITERATOR) {
            $this->dbh = DatabaseConnect::get($this->dbName);
            $this->executeWithDbh($this->dbh);
        } else {
            // 其他查询使用安全的 'call' 模式
            DatabaseConnect::call(fn(MysqliProxy|mysqli $dbh) => $this->executeWithDbh($dbh), $this->dbName);
        }
    }


    /**
     * 使用数据库连接执行SQL，包含统一的异常处理
     * @throws Throwable
     */
    private function executeWithDbh(MysqliProxy|mysqli $dbh): void
    {
        try {
            $this->_mysqlExecSql($dbh);
        } catch (Throwable $e) {
            $this->close(); // 确保在发生异常时也能关闭连接
            throw $e;
        }
    }


    /**
     * @throws Throwable
     */
    private function _mysqlExecSql(MysqliProxy|mysqli $dbh): void
    {
        try {
            // 调试模式, SQL 语句出错,打印到终端
            if (ConfigEnum::APP_PROD === false && $this->debugSql) {
                var_dump($this->sql, implode(',',$this->bindParams));
            }

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
                    throw new AppException(AppErr::DB_UNSUPPORTED_ACTION . ": " . $this->action);
            }

            if ($execRes === false) {
                throw new AppException(AppErr::DB_EXECUTE_FAILED . ": ({$this->stmt->errno}) {$this->stmt->error}");
            }

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
                    throw new AppException(AppErr::DB_UNSUPPORTED_PARAM_TYPE . ": " . gettype($param) . ' value:' . $param);
            }
        }

        if ($typeString) {
            $stmt->bind_param($typeString, ...$params);
        }
    }


}

