<?php
declare(strict_types=1);

namespace Swlib\Connect;

use Exception;
use Generate\ConfigEnum;
use mysqli;
use RuntimeException;
use Swlib\Enum\CtxEnum;
use Swoole\Database\MysqliConfig;
use Swoole\Database\MysqliPool;
use Swoole\Database\MysqliProxy;
use Throwable;

class PoolMysql
{
    use PoolConnectionTrait;

    // 修改: 使用数组来存储多个数据库的连接池
    private static array $pools = [];


    /**
     * @throws Exception
     */
    public static function getDbName(string $dbName = "default"): string
    {
        /** @var array|string $dbConfig */
        $dbConfig = ConfigEnum::DB_DATABASE;
        if ($dbName === 'default') {
            if (is_array($dbConfig)) {
                return $dbConfig[0];
            } elseif (is_string($dbConfig)) {
                return $dbConfig;
            } else {
                throw new Exception("找不到 $dbName 的数据库配置");
            }
        } else {
            if (is_array($dbConfig) && in_array($dbName, $dbConfig)) {
                return $dbName;
            } elseif (is_string($dbConfig) && $dbName == $dbConfig) {
                return $dbConfig;
            } else {
                throw new Exception("找不到 $dbName 的数据库配置");
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function getConfig(string $dbName = 'default'): array
    {
        $dbConfig = ConfigEnum::DB_DATABASE;
        if (is_array($dbConfig)) {
            $resolvedDbName = self::getDbName($dbName);
            // 查找到索引
            $index = array_search($resolvedDbName, $dbConfig);
            if ($index === false) {
                throw new Exception("找不到数据库配置: $resolvedDbName");
            }

            $host = ConfigEnum::DB_HOST[$index]; // 数据库服务器地址
            $port = ConfigEnum::DB_PORT[$index]; // 数据库服务器地址
            $username = ConfigEnum::DB_ROOT[$index]; // 数据库用户名
            $password = ConfigEnum::DB_PWD[$index]; // 数据库密码
            $database = $dbConfig[$index]; // 数据库名称
            $charset = ConfigEnum::DB_CHARSET[$index];
        } else {
            $host = ConfigEnum::DB_HOST; // 数据库服务器地址
            $port = ConfigEnum::DB_PORT; // 数据库服务器地址
            $username = ConfigEnum::DB_ROOT; // 数据库用户名
            $password = ConfigEnum::DB_PWD; // 数据库密码
            $database = $dbConfig; // 数据库名称
            $charset = ConfigEnum::DB_CHARSET;
        }
        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => (string)$password,
            'database' => $database,
            'charset' => $charset,
        ];
    }

    /**
     * @throws Exception
     */
    private static function createPool(string $dbName = 'default'): void
    {
        $conf = self::getConfig($dbName);
        $host = $conf['host']; // 数据库服务器地址
        $port = (int)$conf['port']; // 数据库服务器地址
        $username = $conf['username']; // 数据库用户名
        $password = $conf['password']; // 数据库密码
        $database = $conf['database']; // 数据库名称
        $charset = $conf['charset'];
        $num = ConfigEnum::get('DB_POOL_NUM', 10);

        // 强制最小连接池大小
        if ($num < self::MIN_POOL_SIZE) {
            $num = self::MIN_POOL_SIZE;
        }

        // 修改: 使用数据库名称作为键来存储连接池
        self::$pools[$dbName] = new MysqliPool((new MysqliConfig)
            ->withHost($host)
            ->withPort($port)
            ->withDbName($database)
            ->withCharset($charset)
            ->withUsername($username)
            ->withPassword($password)
            , $num);
    }

    /**
     * @throws Exception
     */
    public static function  get(string $dbName = 'default'): MysqliProxy|mysqli
    {
        // 修改: 检查并创建对应数据库的连接池
        if (!isset(self::$pools[$dbName])) {
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

            $mysqli = self::$pools[$dbName]->get();
            if ($mysqli && $mysqli->stat()) {
                break;
            }
            if ($mysqli) {
                $mysqli->close(); // 直接销毁无效连接
            }
            self::$pools[$dbName]->put(null); //归还一个空连接以保证连接池的数量平衡。
            $count++;
        }
        if (empty($mysqli)) {
            throw new RuntimeException("从连接池获取连接失败");
        }

        return $mysqli;
    }

    public static function put(MysqliProxy|mysqli|null $mysqli, string $dbName = 'default'): void
    {
        if ($mysqli === null) {
            self::$pools[$dbName]->put(null); //归还一个空连接以保证连接池的数量平衡。
            return;
        }

        if ($mysqli->stat()) {
            self::$pools[$dbName]->put($mysqli);
        } else {
            $mysqli->close(); // 销毁无效连接
            self::$pools[$dbName]->put(null); //归还一个空连接以保证连接池的数量平衡。
        }
    }

    /**
     * @throws Throwable
     */
    public static function call(callable $call, string $dbName = 'default'): mixed
    {
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
        // 修改: 关闭所有数据库的连接池
        foreach (self::$pools as $pool) {
            try {
                $pool->close();
            } catch (Throwable) {
                // 忽略关闭过程中的异常，确保所有池都尝试关闭
            }
        }
        self::$pools = [];
    }

    public static function eachDbName(callable $call): void
    {
        /** @var array|string $dbConfig */
        $dbConfig = ConfigEnum::DB_DATABASE;
        if (is_array($dbConfig)) {
            foreach ($dbConfig as $dbName) {
                $call($dbName);
            }
        } else {
            $call($dbConfig);
        }
    }
}