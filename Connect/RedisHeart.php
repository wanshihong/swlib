<?php

namespace Swlib\Connect;

use Generate\ConfigEnum;
use Swoole\Timer;
use Throwable;

class RedisHeart
{

    private static ?int $timer = null;

    public static function start(): void
    {
        self::$timer = Timer::tick(ConfigEnum::DB_HEART * 1000, function () {
            try {
                $redis = PoolRedis::get();
                if (!$redis->ping()) {
                    try {
                        $redis->close();
                    } catch (Throwable) {
                        // 忽略关闭异常
                    }
                    PoolRedis::put(null);
                    return;
                }
                PoolRedis::put($redis);
            } catch (Throwable) {
                try {
                    PoolRedis::put(null);
                } catch (Throwable) {
                    // 忽略异常
                }
            }
        });


    }

    public static function stop(): void
    {
        if (self::$timer !== null) {
            Timer::clear(self::$timer);
            self::$timer = null;
            
            // 在停止心跳时，主动清理连接池
            try {
                PoolRedis::close();
            } catch (Throwable) {
                // 忽略清理时的异常
            }
        }
    }
}