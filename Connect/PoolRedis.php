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

    private static RedisPool|null $pool = null;
    private static int $num = 0;

    private static function createPool(): void
    {
        $host = ConfigEnum::REDIS_HOST;
        $port = ConfigEnum::REDIS_PORT;
        $password = ConfigEnum::REDIS_AUTH;
        $num = ConfigEnum::REDIS_POOL_NUM;

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

        $getCount = 0;
        while (self::$num >= ConfigEnum::REDIS_POOL_NUM) {
            Coroutine::sleep(0.11);
            $getCount++;
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

        $redis = self::get();
        try {
            $ret = $call($redis);
            self::put($redis);
        } catch (Throwable $e) {
            self::put($redis);
            throw $e;
        }
        return $ret;
    }

    /**
     * @throws Throwable
     */
    public static function getSet(string $key, callable $call, int $expire = -1)
    {
        $cacheData = self::call(function (Redis $redis) use ($key) {
            return $redis->get($key);
        });

        if ($cacheData) {
            $arr = unserialize($cacheData);
            return $arr['d'];
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


}