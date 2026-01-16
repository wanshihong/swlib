<?php
declare(strict_types=1);

namespace Swlib\Aop\Aspects;

use Attribute;
use Generate\ConfigEnum;
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;
use Swlib\Table\Trait\PoolRedis;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Throwable;

/**
 * 缓存切面 - 基于 Redis 实现
 *
 * 为方法结果提供自动缓存功能，使用 Redis 作为缓存存储
 *
 * @example 使用示例
 * #[CachingAspect(ttl: 3600)]
 * public function getUserInfo($userId) { }
 *
 * @example 清除缓存示例
 *
 * 方式一：清除指定方法的缓存（推荐）
 * 当需要清除 UserService::getUserInfo(123) 的缓存时：
 *
 * $aspect = new CachingAspect();
 * $aspect->clearCache(new UserService(), 'getUserInfo', [123]);
 *
 * 方式二：清除所有缓存（谨慎使用）
 * 清除该切面前缀下的所有缓存：
 *
 * $aspect = new CachingAspect();
 * $aspect->clearAllCache();
 *
 * 方式三：使用自定义前缀清除缓存
 * 如果在使用注解时指定了自定义前缀：
 *
 * #[CachingAspect(ttl: 3600, prefix: 'member_cache')]
 * public function getUserInfo($memberId) { }
 *
 * 清除时需要使用相同的前缀：
 * $aspect = new CachingAspect(prefix: 'member_cache');
 * $aspect->clearCache(new UserService(), 'getUserInfo', [123]);
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class CachingAspect extends AbstractAspect implements ProxyAttributeInterface
{


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
     * @param int $priority 执行优先级，多个注解时需显式指定
     */
    public function __construct(
        public int    $ttl = 3600,
        public string $prefix = 'aop_cache',
        public int    $priority = 0,
        public bool   $async = false
    )
    {

    }

    /**
     * 环绕通知 - 实现缓存逻辑
     *
     * @param JoinPoint $joinPoint
     * @param callable $next
     * @return mixed
     */
    public function around(JoinPoint $joinPoint, callable $next): mixed
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

            // 检查缓存是否禁用，参考 PoolRedis::getSet 的实现
            if ($cachedValue !== null && !ConfigEnum::get('DISABLED_REDIS_CACHE')) {
                self::$stats['hits']++;
                return $cachedValue;
            }
        } catch (Throwable) {
            // Redis 错误不应该中断业务流程，记录错误并继续执行
            self::$stats['errors']++;
        }

        // 缓存未命中，执行原方法
        self::$stats['misses']++;
        return $next();
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
        // 检查缓存是否禁用，参考 PoolRedis::getSet 的实现
        if (ConfigEnum::get('DISABLED_REDIS_CACHE')) {
            return;
        }

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
        $className = is_object($joinPoint->target) ? get_class($joinPoint->target) : $joinPoint->target;
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

    public function handle(array $ctx, callable $next): mixed
    {
        $joinPoint = new JoinPoint($ctx['target'], $ctx['meta']['method'], $ctx['arguments']);
        return $this->around($joinPoint, static fn() => $next($ctx));
    }
}

