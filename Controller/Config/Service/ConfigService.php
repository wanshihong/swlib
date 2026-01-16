<?php

namespace Swlib\Controller\Config\Service;

use Generate\Tables\CommonApi\ConfigTable;
use Swlib\Connect\PoolRedis;
use Swlib\Crontab\Attribute\CrontabAttribute;
use Swlib\Event\Attribute\Event;
use Swlib\Lock\Attribute\RedisLockAttribute;
use Swlib\Utils\Log;
use Throwable;

class ConfigService
{

    /**
     * 监听到配置发生改变，预热配置项目到缓存中
     * 当数据库发生 UPDATE, INSERT, DELETE 操作时，将所有配置数据写入 Redis 缓存
     * @return void
     */
    #[Event(name: ConfigTable::UpdateAfter)]
    #[Event(name: ConfigTable::InsertAfter)]
    #[Event(name: ConfigTable::DeleteAfter)]
    #[CrontabAttribute(cron: '0 */10 * * *')]
    public function preheatConfig(): void
    {
        try {
            $this->warmupConfigCache();
        } catch (Throwable $e) {
            // 记录缓存预热失败，但不中断业务流程
            Log::save("ConfigService preheatConfig failed: " . $e->getMessage(), 'config_error');
        }
    }

    /**
     * 获取配置
     * 优先从 Redis 缓存读取，缓存未命中时从数据库读取并自动创建缺失的配置项
     * @param string|array $key 配置键名，可以是单个字符串或者字符串数组
     * @param mixed $default 默认值
     * @param int $allowQuery 是否允许查询
     * @param array|string|null $description 自动创建配置项时的描述
     * @return mixed 如果$key是字符串，返回单个配置值；如果$key是数组，返回关联数组[key=>value]
     * @throws Throwable
     */
    public static function get(string|array $key, mixed $default = null, int $allowQuery = 0, string|array|null $description = null, string $type = 'txt'): mixed
    {


        // 如果key是字符串,转换成数组
        $keys = is_string($key) ? [$key] : $key;
        $ret = [];


        foreach ($keys as $k) {
            $ret[$k] = self::getRedisCache($k, function () use ($k, $default, $allowQuery, $description, $type) {
                return self::getConfigByDb($k, $default, $allowQuery, $description, $type);
            });
        }

        if (is_string($key)) {
            return $ret[$key];
        }


        return $ret;
    }

    /**
     * 内部获取配置方法
     * 从数据库读取配置，如果不存在则自动创建
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param int $allowQuery 是否允许查询
     * @param string|null $description 自动创建配置项时的描述
     * @return mixed 配置值
     * @throws Throwable
     */
    #[RedisLockAttribute]
    public static function getConfigByDb(string $key, mixed $default = null, int $allowQuery = 0, ?string $description = null, string $type = 'txt'): mixed
    {
        // 检查配置是否存在
        $where = [
            ConfigTable::KEY => $key,
        ];
        if ($allowQuery) {
            $where[] = [ConfigTable::ALLOW_QUERY, '=', 1];
        }
        $config = new ConfigTable()->where($where)->selectOne();

        // 如果配置不存在，则写入一个新的配置项
        if (empty($config)) {
            $desc = $description ?? '自动创建的配置项';
            try {
                new ConfigTable()->insert([
                    ConfigTable::KEY => $key,
                    ConfigTable::VALUE => $default,  // 默认空值
                    ConfigTable::IS_ENABLE => 1,  // 默认启用
                    ConfigTable::DESC => $desc,  // 使用提供的描述或默认描述
                    ConfigTable::ALLOW_QUERY => $allowQuery,  // 默认不允许查询
                    ConfigTable::VALUE_TYPE => $type,  // 配置类型
                ]);
            } catch (Throwable $e) {
                // 数据库插入失败时记录日志但继续返回默认值
                Log::save("Failed to create config: key=$key, error: " . $e->getMessage(), 'config_error');
            }
            return $default;  // 返回默认值，因为是新创建的配置
        }

        // 如果配置存在且启用，则返回配置值
        if ($config->isEnable == 1) {
            $ret = $config->value; // 使用对象属性而不是数组访问
            return is_numeric($ret) ? $ret : (empty($ret) ? $default : $ret);
        }

        // 配置存在但未启用，返回默认值
        return $default;
    }


    /**
     * 清理配置缓存
     * @param string|array $key 配置键名，可以是单个字符串或者字符串数组
     * @return bool 是否清理成功
     * @throws Throwable
     */
    public static function clearCache(string|array $key): bool
    {
        try {
            // 清理Redis缓存
            self::deleteRedisCache($key);

            // 如果是数组，同时清理每个单独key的缓存（防止单独调用时还有缓存）
            if (is_array($key)) {
                foreach ($key as $singleKey) {
                    self::deleteRedisCache($singleKey);
                }
            }

            return true;
        } catch (Throwable) {
            // 记录日志或处理异常
            return false;
        }
    }


    /**
     * 生成Redis缓存键
     * @param string $key 配置键名
     * @return string 缓存键
     */
    private static function getRedisCacheKey(string $key): string
    {
        return "config:$key";
    }

    /**
     * 获取Redis缓存（带回调）
     * 如果缓存存在则返回缓存值，否则执行回调函数并缓存结果
     * @param string|array $key 配置键名
     * @param callable $callback 缓存不存在时的回调函数
     * @return mixed 缓存数据或回调结果
     * @throws Throwable
     */
    private static function getRedisCache(string|array $key, callable $callback): mixed
    {
        try {
            $cacheKey = self::getRedisCacheKey($key);
            return PoolRedis::getSet($cacheKey, $callback, 86400);
        } catch (Throwable $e) {
            // Redis 连接失败时，直接执行回调获取数据，不使用缓存
            Log::save("Redis cache get failed for key: " . json_encode($key) . ", error: " . $e->getMessage(), 'config_error');
            return call_user_func($callback);
        }
    }

    /**
     * 删除Redis缓存
     * @param string|array $key 配置键名
     * @throws Throwable
     */
    private static function deleteRedisCache(string|array $key): void
    {
        try {
            $cacheKey = self::getRedisCacheKey($key);
            PoolRedis::call(function ($redis) use ($cacheKey) {
                $redis->del($cacheKey);
            });
        } catch (Throwable $e) {
            // Redis 连接失败时记录日志但不中断业务流程
            Log::save("Redis cache delete failed for key: " . json_encode($key) . ", error: " . $e->getMessage(), 'config_error');
        }
    }

    /**
     * 预热配置缓存 - 将所有配置数据写入 Redis
     * 在数据库发生 UPDATE, INSERT, DELETE 操作后调用
     * @return void
     * @throws Throwable
     */
    private function warmupConfigCache(): void
    {
        // 查询所有配置数据
        $configs = new ConfigTable()->selectAll();

        if ($configs->isEmpty()) {
            return;
        }

        // 按 appId 分组缓存配置
        $cacheData = [];
        foreach ($configs as $config) {
            $key = $config->key;

            // 只缓存启用的配置
            if ($config->isEnable == 1) {
                $value = $config->value;
                $cacheData[$key] = is_numeric($value) ? $value : (empty($value) ? null : $value);
            }
        }

        // 将分组后的配置写入 Redis
        foreach ($cacheData as $key => $value) {
            $cacheKey = self::getRedisCacheKey($key);
            // 使用较长的过期时间，避免频繁过期
            // 12 到 36 小时随机，避免批量过期
            PoolRedis::getSet(
                key: $cacheKey,
                call: function () use ($value) {
                    return $value;
                },
                expire: mt_rand(3600 * 12, 3600 * 36),
                forceRefresh: true
            );
        }
    }


}