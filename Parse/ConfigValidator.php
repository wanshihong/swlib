<?php
declare(strict_types=1);

namespace Swlib\Parse;

use FilesystemIterator;
use Generate\ConfigEnum;
use mysqli;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redis;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Parse\Helper\ConsoleColor;
use Throwable;

/**
 * 配置验证器
 * 在项目启动时验证必需的配置项、数据库连接和连接池大小
 */
class ConfigValidator
{
    /**
     * 连接池最小值
     */
    private const int MIN_POOL_SIZE = 5;

    /**
     * 执行所有配置验证
     *
     * @return void
     */
    public static function validate(): void
    {
        ConsoleColor::writeInfo("开始配置验证...");

        // 1. 扫描代码中使用的配置
        self::scanCodeForMissingConfigs();

        // 2. 检测连接池大小
        self::validatePoolSize();

        // 3. 测试数据库连接
        self::testDatabaseConnections();

        ConsoleColor::writeSuccess("✓ 配置验证通过");
    }

    /**
     * 扫描代码中使用的配置，检查是否在 .env 中配置
     *
     * @return void
     */
    private static function scanCodeForMissingConfigs(): void
    {
        ConsoleColor::writeInfo("扫描代码中使用的配置...");

        $appDir = defined('APP_DIR') ? APP_DIR : ROOT_DIR . 'App';

        if (!is_dir($appDir)) {
            ConsoleColor::writeWarning("警告: App 目录不存在，跳过代码扫描");
            return;
        }

        // 扫描所有 PHP 文件
        $phpFiles = self::scanPhpFiles($appDir);

        // 提取所有使用的配置
        $usedConfigs = [];
        foreach ($phpFiles as $file) {
            $configs = self::extractConfigsFromFile($file);
            foreach ($configs as $config) {
                if (!isset($usedConfigs[$config])) {
                    $usedConfigs[$config] = [];
                }
                $usedConfigs[$config][] = $file;
            }
        }

        // 检查哪些配置未定义
        $missingConfigs = array_filter($usedConfigs, function ($configKey) {
            return !defined("Generate\\ConfigEnum::$configKey");
        }, ARRAY_FILTER_USE_KEY);

        if (!empty($missingConfigs)) {
            $errorMessage = "严重错误: 代码中使用了未配置的配置项\n\n";

            foreach ($missingConfigs as $configKey => $files) {
                $errorMessage .= "配置项: $configKey\n";
                $errorMessage .= "使用位置:\n";
                foreach (array_slice($files, 0, 3) as $file) {
                    $relativePath = str_replace(ROOT_DIR, '', $file);
                    $errorMessage .= "  - $relativePath\n";
                }
                if (count($files) > 3) {
                    $errorMessage .= "  - ... 还有 " . (count($files) - 3) . " 个文件\n";
                }
                $errorMessage .= "\n";
            }

            $errorMessage .= "解决方案: 请在 .env 文件中添加以上配置项";

            ConsoleColor::writeErrorHighlight($errorMessage);
            exit(1);
        }

        ConsoleColor::writeSuccess("✓ 代码配置扫描通过（共扫描 " . count($phpFiles) . " 个文件）");
    }

    /**
     * 递归扫描目录中的所有 PHP 文件
     *
     * @param string $dir 目录路径
     * @return array PHP 文件路径列表
     */
    private static function scanPhpFiles(string $dir): array
    {
        $phpFiles = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }

        return $phpFiles;
    }

    /**
     * 从文件中提取使用的配置项
     *
     * @param string $filePath 文件路径
     * @return array 配置项列表
     */
    private static function extractConfigsFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $configs = [];

        // 匹配 ConfigEnum::CONSTANT_NAME
        // 例如: ConfigEnum::DB_HOST, ConfigEnum::REDIS_PORT
        preg_match_all('/ConfigEnum::([A-Z_][A-Z0-9_]*)\b/', $content, $matches1);
        if (!empty($matches1[1])) {
            $configs = array_merge($configs, $matches1[1]);
        }

        // 匹配 ConfigEnum::get('KEY_NAME')
        // 例如: ConfigEnum::get('DB_HOST'), ConfigEnum::get("REDIS_PORT")
        preg_match_all('/ConfigEnum::get\s*\(\s*[\'"]([A-Z_][A-Z0-9_]*)[\'"]/', $content, $matches2);
        if (!empty($matches2[1])) {
            $configs = array_merge($configs, $matches2[1]);
        }

        return array_unique($configs);
    }

    /**
     * 检测连接池大小配置
     * 
     * @return void
     */
    private static function validatePoolSize(): void
    {
        // 检测 Redis 连接池
        $redisPoolNum = ConfigEnum::get('REDIS_POOL_NUM', 0);
        if ($redisPoolNum < self::MIN_POOL_SIZE) {
            ConsoleColor::writeWarning(
                "警告: REDIS_POOL_NUM=$redisPoolNum 过小（最小值: " . self::MIN_POOL_SIZE . "）\n" .
                "已自动调整为 " . self::MIN_POOL_SIZE . "\n" .
                "建议: 在 .env 中设置 REDIS_POOL_NUM=" . self::MIN_POOL_SIZE . " 或更大"
            );
        }

        // 检测 MySQL 连接池（如果配置了）
        $mysqlPoolNum = ConfigEnum::get('DB_POOL_NUM', 0);
        if ($mysqlPoolNum > 0 && $mysqlPoolNum < self::MIN_POOL_SIZE) {
            ConsoleColor::writeWarning(
                "警告: DB_POOL_NUM=$mysqlPoolNum 过小（最小值: " . self::MIN_POOL_SIZE . "）\n" .
                "已自动调整为 " . self::MIN_POOL_SIZE . "\n" .
                "建议: 在 .env 中设置 DB_POOL_NUM=" . self::MIN_POOL_SIZE . " 或更大"
            );
        }
    }

    /**
     * 测试数据库连接
     * 
     * @return void
     */
    private static function testDatabaseConnections(): void
    {
        // 测试 MySQL 连接
        self::testMysqlConnection();

        // 测试 Redis 连接
        self::testRedisConnection();
    }

    /**
     * 测试 MySQL 连接
     * 
     * @return void
     */
    private static function testMysqlConnection(): void
    {
        if (!extension_loaded('mysqli')) {
            ConsoleColor::writeErrorHighlight(
                "严重错误: 缺少 mysqli 扩展\n" .
                "请安装 PHP mysqli 扩展"
            );
            exit(1);
        }

        $host = ConfigEnum::get('DB_HOST');
        $port = ConfigEnum::get('DB_PORT');
        $database = ConfigEnum::get('DB_DATABASE');
        $username = ConfigEnum::get('DB_ROOT');
        $password = ConfigEnum::get('DB_PWD');

        ConsoleColor::writeInfo("测试 MySQL 连接: $username@$host:$port/$database");

        $mysqli = @new mysqli($host, $username, $password, $database, $port);

        if ($mysqli->connect_error) {
            ConsoleColor::writeErrorHighlight(
                "严重错误: MySQL 连接失败\n" .
                "错误信息: $mysqli->connect_error\n" .
                "连接信息: $username@$host:$port/$database\n" .
                "请检查:\n" .
                "  1. MySQL 服务是否启动\n" .
                "  2. 数据库配置是否正确\n" .
                "  3. 用户名密码是否正确\n" .
                "  4. 数据库是否存在"
            );
            exit(1);
        }

        $mysqli->close();
        ConsoleColor::writeSuccess("✓ MySQL 连接成功");
    }

    /**
     * 测试 Redis 连接
     *
     * @return void
     */
    private static function testRedisConnection(): void
    {
        if (!extension_loaded('redis')) {
            ConsoleColor::writeErrorHighlight(
                "严重错误: 缺少 redis 扩展\n" .
                "请安装 PHP redis 扩展"
            );
            exit(1);
        }

        $host = ConfigEnum::get('REDIS_HOST');
        $port = ConfigEnum::get('REDIS_PORT');
        $auth = ConfigEnum::get('REDIS_AUTH');

        ConsoleColor::writeInfo("测试 Redis 连接: $host:$port");

        $redis = new Redis();

        try {
            $connected = @$redis->connect($host, $port, 2.0);

            if (!$connected) {
                throw new AppException(AppErr::CONFIG_CONNECT_FAILED);
            }

            // 如果配置了密码，进行认证
            if (!empty($auth)) {
                $authed = @$redis->auth($auth);
                if (!$authed) {
                    throw new AppException(AppErr::CONFIG_AUTH_FAILED);
                }
            }

            // 测试 PING
            $pong = @$redis->ping();
            if ($pong !== true && $pong !== '+PONG') {
                throw new AppException(AppErr::CONFIG_PING_FAILED);
            }

            $redis->close();
            ConsoleColor::writeSuccess("✓ Redis 连接成功");

        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
            ConsoleColor::writeErrorHighlight(
                "严重错误: Redis 连接失败\n" .
                "错误信息: $errorMsg\n" .
                "连接信息: $host:$port\n" .
                "请检查:\n" .
                "  1. Redis 服务是否启动\n" .
                "  2. Redis 配置是否正确\n" .
                "  3. Redis 密码是否正确（REDIS_AUTH）\n" .
                "  4. 防火墙是否允许连接"
            );
            exit(1);
        }
    }
}
