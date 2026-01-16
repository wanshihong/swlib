<?php
declare(strict_types=1);

namespace Swlib\Connect;


use Generate\ConfigEnum;
use Redis;
use Swlib\Table\Trait\PoolConnectionTrait;
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

        $startTime = microtime(true);
        $getCount = 0;
        $poolSize = max(ConfigEnum::REDIS_POOL_NUM, self::MIN_POOL_SIZE);

        while (self::$num >= $poolSize) {
            Coroutine::sleep(0.11);
            $getCount++;

            // 检查是否超时
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
                break;
            }
        }

        // 获取连接
        $redis = self::$pool->get();
        self::$num++;
        return $redis;
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

        // 检测嵌套深度（如果 >= 连接池大小会直接抛出异常）
        $depth = self::checkNestDepth('redis_call_depth', $poolSize, 'Redis');

        // 如果嵌套深度过大，记录警告
        if ($depth > self::MAX_NEST_DEPTH) {
            self::logNestWarning($depth, 'Redis', 'redis_pool');
        }

        try {
            $redis = self::get();
            try {
                $ret = $call($redis);
                self::put($redis);
            } catch (Throwable $e) {
                self::put($redis);
                throw $e;
            }
            return $ret;
        } finally {
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
        // 如果不强制刷新，先尝试从缓存获取
        if ($forceRefresh === false) {
            $cacheData = self::call(function (Redis $redis) use ($key) {
                return $redis->get($key);
            });

            if ($cacheData && !ConfigEnum::get('DISABLED_REDIS_CACHE')) {
                // 尝试反序列化，如果失败说明数据格式不对，需要重新生成
                try {
                    $arr = unserialize($cacheData);
                    if (is_array($arr) && isset($arr['d'])) {
                        return $arr['d'];
                    }
                } catch (Throwable) {
                    // 反序列化失败，继续执行回调重新生成缓存
                }
            }
        }

        // 执行回调获取数据并写入缓存
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


}