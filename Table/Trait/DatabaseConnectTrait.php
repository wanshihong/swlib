<?php
declare(strict_types=1);

namespace Swlib\Table\Trait;

use Exception;
use Generate\ConfigEnum;
use mysqli;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Table\Connect\MysqlConnect;
use Swoole\Database\MysqliPool;
use Swoole\Database\MysqliProxy;
use Throwable;

trait DatabaseConnectTrait
{
    use PoolConnectionTrait;

    private static array $pools = [];

    public static function getDbName(string $dbName = "default"): string
    {
        if ($dbName === 'default') {
            foreach (self::config as $name => $config) {
                return $name;
            }
        }
        return $dbName;
    }

    public static function getConfig(string $dbName = "default"): array
    {
        $dbName = self::getDbName($dbName);
        return self::config[$dbName];
    }

    public static function getPool(string $dbName = "default")
    {
        $dbName = self::getDbName($dbName);
        return self::$pools[$dbName] ?? null;
    }

    /**
     * @throws Exception
     */
    private static function createPool(string $dbName = 'default'): void
    {
        $config = self::getConfig($dbName);
        $database = $config['database'] ?? ($config['dbname'] ?? $dbName);
        $port = (int)($config['port'] ?? 3306);
        $pool = MysqlConnect::createPool(
            host: $config['host'],
            port: $port,
            database: $database,
            user: $config['user'],
            pass: $config['pass'],
            charset: $config['charset'] ?? 'utf8',
            poolNum: ConfigEnum::DB_POOL_NUM
        );
        self::$pools[$dbName] = $pool;
    }


    /**
     * @throws Exception
     */
    public static function connect(string $dbName = 'default'): mysqli
    {
        $config = self::getConfig($dbName);
        $database = $config['database'] ?? ($config['dbname'] ?? $dbName);
        return MysqlConnect::connect(
            host: $config['host'],
            port: (int)($config['port'] ?? 3306),
            database: $database,
            user: $config['user'],
            pass: $config['pass'],
        );
    }

    /**
     * @throws Exception
     */
    public static function get(string $dbName = 'default'): MysqliProxy|mysqli
    {
        $dbName = self::getDbName($dbName);
        if (empty(self::getPool($dbName))) {
            self::createPool($dbName);
        }

        $startTime = microtime(true);
        $mysqli = null;
        $count = 0;

        while ($count < 3) {
            // 检查是否超时
            $elapsed = self::checkTimeout($startTime);
            if ($elapsed >= self::GET_TIMEOUT) {
                $poolSize = ConfigEnum::get('DB_POOL_NUM', 10);
                self::throwTimeoutException(
                    $elapsed,
                    'MySQL',
                    'mysql_call_depth',
                    max($poolSize, self::MIN_POOL_SIZE),
                    "数据库: $dbName"
                );
            }

            $mysqli = self::getPool($dbName)->get();
            if ($mysqli && $mysqli->stat()) {
                break;
            }
            if ($mysqli) {
                $mysqli->close(); // 直接销毁无效连接
            }
            self::getPool($dbName)->put(null); //归还一个空连接以保证连接池的数量平衡。
            $count++;
        }
        if (empty($mysqli)) {
            throw new AppException(LanguageEnum::DB_POOL_GET_CONNECTION_FAILED);
        }

        return $mysqli;
    }

    public static function put(MysqliProxy|mysqli|null $mysqli, string $dbName = 'default'): void
    {
        $dbName = self::getDbName($dbName);
        if ($mysqli === null) {
            self::getPool($dbName)->put(null); //归还一个空连接以保证连接池的数量平衡。
            return;
        }

        if ($mysqli->stat()) {
            self::getPool($dbName)->put($mysqli);
        } else {
            $mysqli->close(); // 销毁无效连接
            self::getPool($dbName)->put(null); //归还一个空连接以保证连接池的数量平衡。
        }
    }

    /**
     * @throws Throwable
     */
    public static function call(callable $call, string $dbName = 'default'): mixed
    {
        $dbName = self::getDbName($dbName);
        // 获取连接池大小
        $poolSize = max(ConfigEnum::get('DB_POOL_NUM', 10), self::MIN_POOL_SIZE);

        // 检测嵌套深度（如果 >= 连接池大小会直接抛出异常）
        $depth = self::checkNestDepth('mysql_call_depth', $poolSize, 'MySQL');

        // 如果嵌套深度过大，记录警告
        if ($depth > self::MAX_NEST_DEPTH) {
            self::logNestWarning($depth, 'MySQL', 'mysql_pool', "数据库: $dbName");
        }

        try {
            $mysqli = self::get($dbName);
            try {
                $ret = $call($mysqli);
                self::put($mysqli, $dbName);
            } catch (Throwable $e) {
                self::put($mysqli, $dbName);
                throw $e;
            }
            return $ret;
        } finally {
            self::decreaseNestDepth('mysql_call_depth', $depth);
        }
    }

    /**
     * @throws Throwable
     */
    public static function query($sql, string $dbName = 'default')
    {
        $dbh = CtxEnum::TransactionDbh->get();
        if ($dbh) {
            return $dbh->query($sql);
        }
        return self::call(function ($mysqli) use ($sql) {
            return $mysqli->query($sql);
        }, $dbName);
    }

    public static function close(): void
    {
        foreach (self::$pools as $pool) {
            try {
                if ($pool instanceof MysqliPool) {
                    $pool->close();
                }
            } catch (Throwable) {
            }
        }
        self::$pools = [];

    }

    public static function eachDbName(callable $call): void
    {
        foreach (self::config as $dbName => $config) {
            $call($dbName);
        }
    }

    public static function getNamespace(string $dbName = 'default'): string
    {
        $config = self::getConfig($dbName);
        return $config['namespace'] ?? '';
    }

}
