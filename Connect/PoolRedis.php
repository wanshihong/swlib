<?php
declare(strict_types=1);

namespace Swlib\Connect;


use Generate\ConfigEnum;
use Redis;
use Swoole\Coroutine;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use Throwable;

class PoolRedis
{
    use PoolConnectionTrait;

    private static RedisPool|null $pool = null;
    private static int $num = 0;

    private static function createPool(): void
    {
        $host = ConfigEnum::REDIS_HOST;
        $port = ConfigEnum::REDIS_PORT;
        $password = ConfigEnum::REDIS_AUTH;
        $num = ConfigEnum::REDIS_POOL_NUM;

        // 强制最小连接池大小
        if ($num < self::MIN_POOL_SIZE) {
            $num = self::MIN_POOL_SIZE;
        }

        self::$pool = new RedisPool((new RedisConfig)
            ->withHost($host)
            ->withPort($port)
            ->withAuth($password)
            ->withDbIndex(0)
            ->withTimeout(1)
            , $num);
    }


    public static function get(): Redis
    {
        if (!self::$pool) {
            self::createPool();
        }

        return self::waitForConnection();
    }

    public static function put(Redis|null $redis): void
    {
        self::$pool->put($redis);
        self::$num--;
    }


    /**
     * @throws Throwable
     */
    public static function call(callable $call): mixed
    {
        // 获取连接池大小
        $poolSize = max(ConfigEnum::REDIS_POOL_NUM, self::MIN_POOL_SIZE);
        $depth = self::checkNestDepth('redis_call_depth', $poolSize, 'Redis');
        if ($depth > self::MAX_NEST_DEPTH) {
            self::logNestWarning($depth, 'Redis', 'redis_pool');
        }

        $redis = null;
        try {
            $redis = self::get();
            return $call($redis);
        } finally {
            if ($redis !== null) {
                self::put($redis);
            }
            self::decreaseNestDepth('redis_call_depth', $depth);
        }
    }

    /**
     * @param string $key 缓存键
     * @param callable $call 获取数据的回调函数
     * @param int $expire 过期时间（秒），-1表示随机过期时间
     * @param bool $forceRefresh 是否强制刷新缓存，跳过缓存直接执行回调
     * @throws Throwable
     */
    public static function getSet(string $key, callable $call, int $expire = -1, bool $forceRefresh = false): mixed
    {
        if ($forceRefresh === false) {
            $cacheData = self::call(function (Redis $redis) use ($key) {
                return $redis->get($key);
            });

            if ($cacheData && !ConfigEnum::get('DISABLED_REDIS_CACHE')) {
                try {
                    $arr = unserialize($cacheData);
                    if (is_array($arr) && isset($arr['d'])) {
                        return $arr['d'];
                    }
                } catch (Throwable) {
                }
            }
        }

        return self::call(function (Redis $redis) use ($key, $call, $expire) {
            $res = $call();
            if ($res) {
                $redis->set($key, serialize(['d' => $res]));
                $redis->expire($key, $expire === -1 ? mt_rand(3600, 3600 * 4) : $expire);
            }
            return $res;
        });
    }

    public static function close(): void
    {
        self::$pool?->close();
        self::$pool = null;
    }

    private static function waitForConnection(): Redis
    {
        $startTime = microtime(true);
        $getCount = 0;
        $poolSize = max(ConfigEnum::REDIS_POOL_NUM, self::MIN_POOL_SIZE);

        while (self::$num >= $poolSize) {
            Coroutine::sleep(0.11);
            $getCount++;

            $elapsed = self::checkTimeout($startTime);
            if ($elapsed >= self::GET_TIMEOUT) {
                self::throwTimeoutException(
                    $elapsed,
                    'Redis',
                    'redis_call_depth',
                    $poolSize
                );
            }

            if ($getCount >= 10) {
                self::$pool->fill();
                $getCount = 0;
            }
        }

        $redis = self::$pool->get();
        self::$num++;
        return $redis;
    }
}
