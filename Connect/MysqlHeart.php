<?php

namespace Swlib\Connect;

use Generate\ConfigEnum;
use Generate\DatabaseConnect;
use Swoole\Timer;
use Throwable;

class MysqlHeart
{
    private static ?int $timer = null;

    public static function start(): void
    {
        self::$timer = Timer::tick(ConfigEnum::DB_HEART * 1000, function () {
            // 遍历所有数据库连接池进行心跳检测
            DatabaseConnect::eachDbName(function ($dbName) {
                $dbh = null;
                try {
                    $dbh = DatabaseConnect::get($dbName);
                    
                    $r = $dbh->query("SELECT 1 AS result")->fetch_assoc();
                    if ($r['result'] != 1) { // 使用宽松比较，因为MySQL可能返回数字或字符串
                        $dbh->close();
                        DatabaseConnect::put(null, $dbName);
                        return;
                    }
                    DatabaseConnect::put($dbh, $dbName);
                } catch (Throwable) {
                    // 确保在发生异常时关闭连接并记录日志
                    if ($dbh !== null) {
                        try {
                            $dbh->close();
                        } catch (Throwable) {
                            // 忽略关闭时的异常
                        }
                        DatabaseConnect::put(null, $dbName);
                    }
                }
            });
        });
    }

    public static function stop(): void
    {
        if (self::$timer !== null) {
            Timer::clear(self::$timer);
            self::$timer = null;
            
            // 在停止心跳时，主动清理连接池中的所有连接
            try {
                DatabaseConnect::close();
            } catch (Throwable) {
                // 忽略清理时的异常
            }
        }
    }
}
