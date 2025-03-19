<?php

namespace Swlib\Connect;

use Generate\ConfigEnum;
use Swoole\Timer;
use Throwable;

class RedisHeart
{

    private static int $timer;

    public static function start(): void
    {
        self::$timer = Timer::tick(ConfigEnum::DB_HEART * 1000, function () {
            try {
                $redis = PoolRedis::get();
                if (!$redis->ping()) {
                    PoolRedis::put(null);
                    return;
                }
                PoolRedis::put($redis);
            } catch (Throwable) {
                PoolMysql::put(null);
            }
        });


    }

    public static function stop(): void
    {
        if (self::$timer) {
            Timer::clear(self::$timer);
        }

    }
}