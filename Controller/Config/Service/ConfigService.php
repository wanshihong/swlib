<?php

declare(strict_types=1);

namespace Swlib\Controller\Config\Service;

use Generate\ConfigMap;
use Generate\Tables\Main\ConfigTable;
use Swlib\Event\Attribute\Event;
use Swlib\Lock\Attribute\RedisLockAttribute;
use Swlib\Parse\Config\ParseDatabasesConfig;
use Swlib\Utils\Log;
use Throwable;

class ConfigService
{
    /**
     * 监听到配置发生改变，重新生成静态文件
     */
    #[Event(name: ConfigTable::UpdateAfter)]
    #[Event(name: ConfigTable::InsertAfter)]
    #[Event(name: ConfigTable::DeleteAfter)]
    public function regenerateConfigMap(): void
    {
        try {
            new ParseDatabasesConfig();
        } catch (Throwable $e) {
            Log::save("ConfigMap regenerate failed: " . $e->getMessage(), 'config_error');
        }
    }

    /**
     * 获取配置值
     * @param string|array $key 配置键名，可以是单个字符串或者字符串数组
     * @param mixed $default 默认值
     * @param bool $checkEnable 是否检查启用状态（默认 true）
     * @return mixed 如果$key是字符串，返回单个配置值；如果$key是数组，返回关联数组[key=>value]
     * @throws Throwable
     */
    public static function get(string|array $key, mixed $default = null, bool $checkEnable = true, string $desc = ''): mixed
    {
        $keys = is_string($key) ? [$key] : $key;
        $ret = [];

        foreach ($keys as $k) {
            $config = ConfigMap::$configs[$k] ?? null;

            if ($config === null) {
                // 静态文件不存在，尝试从数据库创建
                $ret[$k] = self::createConfigFromDb($k, $default, $desc);
            } elseif ($checkEnable && $config['is_enable'] != 1) {
                $ret[$k] = $default;
            } else {
                $value = $config['value'];
                $ret[$k] = is_numeric($value) ? $value : (empty($value) ? $default : $value);
            }
        }

        return is_string($key) ? $ret[$key] : $ret;
    }

    /**
     * 获取原始配置信息（不管是否启用）
     * @param string $key 配置键名
     * @return array|null 返回完整配置信息或 null
     */
    public static function getRaw(string $key): ?array
    {
        return ConfigMap::$configs[$key] ?? null;
    }

    /**
     * 从数据库创建配置并重新生成静态文件
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param string $desc
     * @return mixed
     * @throws Throwable
     */
    #[RedisLockAttribute]
    private static function createConfigFromDb(string $key, mixed $default, string $desc): mixed
    {
        $config = new ConfigTable()->where([ConfigTable::KEY => $key])->selectOne();

        if (empty($config)) {
            try {
                new ConfigTable()->insert([
                    ConfigTable::KEY => $key,
                    ConfigTable::VALUE => $default,
                    ConfigTable::IS_ENABLE => 1,
                    ConfigTable::DESC => $desc ?: '自动创建的配置项',
                    ConfigTable::ALLOW_QUERY => 0,
                    ConfigTable::VALUE_TYPE => 'txt',
                ]);
            } catch (Throwable $e) {
                Log::save("Failed to create config: key=$key, error: " . $e->getMessage(), 'config_error');
            }
            // 创建后重新生成静态文件
            new ParseDatabasesConfig();
            return $default;
        }

        // 如果配置存在但静态文件中缺失，重新生成静态文件
        new ParseDatabasesConfig();

        if ($config->isEnable == 1) {
            $ret = $config->value;
            return is_numeric($ret) ? $ret : (empty($ret) ? $default : $ret);
        }

        return $default;
    }
}
