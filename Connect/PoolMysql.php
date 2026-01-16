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

    private static array $pools = [];

    private const string DEFAULT_SCHEMA_KEY = 'main';
    private const string CONFIG_ERROR_HINT = '请确认已重启服务并成功生成 runtime/Generate/ConfigEnum.php，且 .env 中已配置 DB_DATABASE/DB_HOST/DB_PORT/DB_ROOT/DB_PWD/DB_CHARSET。';

    /**
     * @throws Exception
     */
    public static function getDbName(string $dbName = "default"): string
    {
        return self::resolveSchemaKey($dbName);
    }

    /**
     * @throws Exception
     */
    public static function getConfig(string $dbName = 'default'): array
    {
        $schemaKey = self::resolveSchemaKey($dbName);

        $databases = ConfigEnum::get('DATABASES');
        if (!is_array($databases) || empty($databases)) {
            throw new Exception('数据库配置错误: DATABASES 未生成或为空，' . self::CONFIG_ERROR_HINT);
        }

        if (!isset($databases[$schemaKey]) || !is_array($databases[$schemaKey])) {
            throw new Exception("找不到数据库配置: $schemaKey");
        }

        $conf = $databases[$schemaKey];
        $host = $conf['host'] ?? null;
        $port = $conf['port'] ?? null;
        $username = $conf['username'] ?? null;
        $password = $conf['password'] ?? '';
        $database = $conf['database'] ?? null;
        $charset = $conf['charset'] ?? null;

        if ($host === null || $port === null || $username === null || $database === null || $charset === null) {
            throw new Exception("数据库配置错误: [$schemaKey] 配置不完整，" . self::CONFIG_ERROR_HINT);
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
        $dbName = self::resolveSchemaKey($dbName);
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
        $dbName = self::resolveSchemaKey($dbName);
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
        $dbName = self::resolveSchemaKey($dbName);
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
        $dbName = self::resolveSchemaKey($dbName);
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
                $pool->close();
            } catch (Throwable) {
                // 忽略关闭过程中的异常，确保所有池都尝试关闭
            }
        }
        self::$pools = [];
    }

    public static function eachDbName(callable $call): void
    {
        $databases = ConfigEnum::get('DATABASES');
        if (!is_array($databases) || empty($databases)) {
            throw new RuntimeException('数据库配置错误: DATABASES 未生成或为空，' . self::CONFIG_ERROR_HINT);
        }
        foreach ($databases as $conf) {
            if (is_array($conf) && isset($conf['database'])) {
                $call($conf['database']);
            }
        }
    }

    public static function eachSchemaKey(callable $call): void
    {
        $databases = ConfigEnum::get('DATABASES');
        if (!is_array($databases) || empty($databases)) {
            throw new RuntimeException('数据库配置错误: DATABASES 未生成或为空，' . self::CONFIG_ERROR_HINT);
        }
        foreach (array_keys($databases) as $schemaKey) {
            $call((string)$schemaKey);
        }
    }

    /**
     * @throws Exception
     */
    private static function resolveSchemaKey(string $dbName): string
    {
        if ($dbName === 'default') {
            $databases = ConfigEnum::get('DATABASES');
            if (!is_array($databases) || empty($databases)) {
                return self::DEFAULT_SCHEMA_KEY;
            }
            $firstKey = array_key_first($databases);
            return $firstKey === null ? self::DEFAULT_SCHEMA_KEY : (string)$firstKey;
        }

        $databases = ConfigEnum::get('DATABASES');
        if (is_array($databases) && array_key_exists($dbName, $databases)) {
            return $dbName;
        }

        if (!is_array($databases)) {
            throw new Exception("找不到 $dbName 的数据库配置");
        }

        foreach ($databases as $schemaKey => $conf) {
            if (!is_array($conf) || !isset($conf['database'])) {
                continue;
            }
            if ($conf['database'] === $dbName) {
                return (string)$schemaKey;
            }
        }

        throw new Exception("找不到 $dbName 的数据库配置");
    }
}
