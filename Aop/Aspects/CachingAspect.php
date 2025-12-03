<?php
declare(strict_types=1);

namespace Swlib\Aop\Aspects;

use Swlib\Aop\Abstract\AbstractAspect;
use Attribute;

use Swlib\Aop\JoinPoint;
use Swlib\Connect\PoolRedis;
use Throwable;

/**
 * 缓存切面 - 基于 Redis 实现
 *
 * 为方法结果提供自动缓存功能，使用 Redis 作为缓存存储
 *
 * @example
 * #[CachingAspect(ttl: 3600)]
 * public function getUserInfo($userId) { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class CachingAspect extends AbstractAspect
{
    /**
     * @var int 缓存过期时间（秒）
     */
    private int $ttl;

    /**
     * @var string 缓存键前缀
     */
    private string $prefix;

    /**
     * @var array 缓存统计
     */
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'errors' => 0,
    ];

    /**
     * 构造函数
     *
     * @param int $ttl 缓存过期时间（秒），默认 3600
     * @param string $prefix 缓存键前缀，默认 'aop_cache'
     */
    public function __construct(int $ttl = 3600, string $prefix = 'aop_cache')
    {
        $this->ttl = $ttl;
        $this->prefix = $prefix;
    }

    /**
     * 环绕通知 - 实现缓存逻辑
     *
     * @param JoinPoint $joinPoint
     * @return mixed|null
     */
    public function around(JoinPoint $joinPoint): mixed
    {
        // 生成缓存键
        $cacheKey = $this->generateCacheKey($joinPoint);

        try {
            // 尝试从 Redis 缓存获取
            $cachedValue = PoolRedis::call(function ($redis) use ($cacheKey) {
                $data = $redis->get($cacheKey);
                if ($data === false) {
                    return null;
                }
                return unserialize($data);
            });

            if ($cachedValue !== null) {
                self::$stats['hits']++;
                return $cachedValue;
            }
        } catch (Throwable) {
            // Redis 错误不应该中断业务流程，记录错误并继续执行
            self::$stats['errors']++;
        }

        // 缓存未命中，返回 null 继续执行原方法
        self::$stats['misses']++;
        return null;
    }

    /**
     * 后置通知 - 保存结果到 Redis 缓存
     *
     * @param JoinPoint $joinPoint
     * @param mixed $result
     * @return void
     */
    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        $cacheKey = $this->generateCacheKey($joinPoint);

        try {
            PoolRedis::call(function ($redis) use ($cacheKey, $result) {
                $serialized = serialize($result);
                $redis->set($cacheKey, $serialized);
                $redis->expire($cacheKey, $this->ttl);
            });
        } catch (Throwable) {
            // Redis 错误不应该中断业务流程
            self::$stats['errors']++;
        }
    }

    /**
     * 生成缓存键
     *
     * @param JoinPoint $joinPoint
     * @return string
     */
    private function generateCacheKey(JoinPoint $joinPoint): string
    {
        $className = get_class($joinPoint->target);
        $methodName = $joinPoint->methodName;
        $arguments = $joinPoint->arguments;

        // 使用类名、方法名和参数生成唯一键
        return $this->prefix . ':' . $className . ':' . $methodName . ':' . md5(serialize($arguments));
    }

    /**
     * 清除指定方法的缓存
     *
     * @param object $target 目标对象
     * @param string $methodName 方法名
     * @param array $arguments 参数
     * @return void
     */
    public function clearCache(object $target, string $methodName, array $arguments = []): void
    {
        $joinPoint = new JoinPoint($target, $methodName, $arguments);
        $cacheKey = $this->generateCacheKey($joinPoint);

        try {
            PoolRedis::call(function ($redis) use ($cacheKey) {
                $redis->del($cacheKey);
            });
        } catch (Throwable) {
            self::$stats['errors']++;
        }
    }

    /**
     * 清除所有缓存（按前缀）
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        try {
            PoolRedis::call(function ($redis) {
                // 使用 SCAN 命令扫描所有匹配前缀的键
                $pattern = $this->prefix . ':*';
                $cursor = 0;

                do {
                    $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
                    if ($result === false) {
                        break;
                    }

                    $cursor = $result[0];
                    $keys = $result[1];

                    if (!empty($keys)) {
                        $redis->del(...$keys);
                    }
                } while ($cursor !== 0);
            });
        } catch (Throwable) {
            self::$stats['errors']++;
        }
    }

    /**
     * 获取缓存统计信息
     *
     * @return array
     */
    public static function getStats(): array
    {
        return self::$stats;
    }

    /**
     * 重置统计信息
     *
     * @return void
     */
    public static function resetStats(): void
    {
        self::$stats = ['hits' => 0, 'misses' => 0, 'errors' => 0];
    }

    /**
     * 获取缓存命中率
     *
     * @return float
     */
    public static function getHitRate(): float
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        return $total > 0 ? (self::$stats['hits'] / $total) * 100 : 0.0;
    }
}

