<?php
declare(strict_types=1);

namespace Swlib\Connect;

use Exception;
use Generate\ConfigEnum;
use mysqli;
use RuntimeException;
use Swoole\Database\MysqliConfig;
use Swoole\Database\MysqliPool;
use Swoole\Database\MysqliProxy;
use Throwable;

class PoolMysql
{
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
        if (is_array(ConfigEnum::DB_DATABASE)) {
            $dbName = self::getDbName($dbName);
            // 查找到索引
            $index = array_search($dbName, ConfigEnum::DB_DATABASE);

            $host = ConfigEnum::DB_HOST[$index]; // 数据库服务器地址
            $port = ConfigEnum::DB_PORT[$index]; // 数据库服务器地址
            $username = ConfigEnum::DB_ROOT[$index]; // 数据库用户名
            $password = ConfigEnum::DB_PWD[$index]; // 数据库密码
            $database = ConfigEnum::DB_DATABASE[$index]; // 数据库名称
            $charset = ConfigEnum::DB_CHARSET[$index];
        } else {
            $host = ConfigEnum::DB_HOST; // 数据库服务器地址
            $port = ConfigEnum::DB_PORT; // 数据库服务器地址
            $username = ConfigEnum::DB_ROOT; // 数据库用户名
            $password = ConfigEnum::DB_PWD; // 数据库密码
            $database = ConfigEnum::DB_DATABASE; // 数据库名称
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
        $num = ConfigEnum::DB_POOL_NUM;

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
    public static function get(string $dbName = 'default'): MysqliProxy|mysqli
    {
        // 修改: 检查并创建对应数据库的连接池
        if (!isset(self::$pools[$dbName])) {
            self::createPool($dbName);
        }

        $mysqli = null;
        $count = 0;
        while ($count < 3) {
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
        $mysqli = self::get($dbName);
        try {
            $ret = $call($mysqli);
            self::put($mysqli, $dbName);
        } catch (Throwable $e) {
            self::put($mysqli, $dbName);
            throw $e;
        }
        return $ret;
    }

    /**
     * @throws Throwable
     */
    public static function query($sql, string $dbName = 'default')
    {
        return self::call(function ($mysqli) use ($sql) {
            return $mysqli->query($sql);
        }, $dbName);
    }

    public static function close(): void
    {
        // 修改: 关闭所有数据库的连接池
        foreach (self::$pools as $pool) {
            $pool->close();
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