<?php

namespace Swlib\Connect;

use Generate\ConfigEnum;
use Swoole\Timer;
use Throwable;

class MysqlHeart
{
    private static ?int $timer = null;

    public static function start(): void
    {
        self::$timer = Timer::tick(ConfigEnum::DB_HEART * 1000, function () {
            // 遍历所有数据库连接池进行心跳检测
            PoolMysql::eachDbName(function ($dbName) {
                $dbh = null;
                try {
                    $dbh = PoolMysql::get($dbName);
                    
                    $r = $dbh->query("SELECT 1 AS result")->fetch_assoc();
                    if ($r['result'] != 1) { // 使用宽松比较，因为MySQL可能返回数字或字符串
                        $dbh->close();
                        PoolMysql::put(null, $dbName);
                        return;
                    }
                    PoolMysql::put($dbh, $dbName);
                } catch (Throwable) {
                    PoolMysql::put(null, $dbName);
                    $dbh?->close();
                }
            });
        });
    }

    public static function stop(): void
    {
        if (self::$timer !== null) {
            Timer::clear(self::$timer);
        }
    }
}
