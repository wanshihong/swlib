<?php

namespace Swlib\DataManager;


/**
 * 单进程内数据管理容器；
 * 不可用于多进程共享数据场景；
 */
class WorkerManager
{
    public static array $pool = [];

    public static function get(string $key)
    {
        $key = static::getProcessKey($key);
        if (isset(static::$pool[$key])) {
            return static::$pool[$key];
        }
        return null;
    }

    public static function set(string $key, mixed $item): void
    {
        $key = static::getProcessKey($key);
        static::$pool[$key] = $item;
    }


    public static function push(string $key, mixed $item): void
    {
        $key = static::getProcessKey($key);
        if (empty(static::$pool[$key])) {
            static::$pool[$key] = [];
        }
        static::$pool[$key][] = $item;
    }

    public static function delete(string $key): void
    {
        $key = static::getProcessKey($key);
        unset(static::$pool[$key]);
    }

    public static function clear(): void
    {
        static::$pool = [];
    }

    public static function getSet(string $key, callable $callback)
    {
        $key = static::getProcessKey($key);
        if ($ret = self::get($key)) {
            return $ret;
        }
        $ret = $callback();
        if ($ret) {
            self::set($key, $ret);
        }
        return $ret;
    }

    private static function getProcessKey(string $key): string
    {
        return posix_getpid() . ':' . $key;
    }
}