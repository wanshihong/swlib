<?php
declare(strict_types=1);

namespace Swlib\Utils\Lock;


use Redis;
use RuntimeException;
use Swlib\Connect\PoolRedis;
use Swoole\Coroutine;
use Throwable;

class RedisLock
{


    /**
     * 获取Redis排他锁
     *
     * @param string $lockKey 锁的键名
     * @param int $ttl 锁的超时时间（毫秒）
     * @param string|null $value 锁的值，默认为随机字符串
     * @return bool 是否成功获取锁
     * @throws Throwable
     */
    public static function lock(string $lockKey, int $ttl = 10000, ?string $value = null): bool
    {
        $value = $value ?: uniqid('', true);
        return PoolRedis::call(function (Redis $redis) use ($lockKey, $ttl, $value) {
            // 使用SET命令的NX和PX选项实现排他锁
            // NX: 只有当key不存在时才设置值
            // PX: 设置过期时间（毫秒）
            $result = $redis->set($lockKey, $value, ['NX', 'PX' => $ttl]);
            if ($result) {
                // 存储锁值，用于后续解锁验证
                Coroutine::getContext()['redis_lock_' . $lockKey] = $value;
            }
            return (bool)$result;
        });
    }

    /**
     * 释放Redis排他锁
     *
     * @param string $lockKey 锁的键名
     * @return bool 是否成功释放锁
     * @throws Throwable
     */
    public static function unlock(string $lockKey): bool
    {
        $lockValue = Coroutine::getContext()['redis_lock_' . $lockKey] ?? null;
        if (!$lockValue) {
            return false; // 没有找到锁值，可能不是当前协程加的锁
        }

        return PoolRedis::call(function (Redis $redis) use ($lockKey, $lockValue) {
            // 使用Lua脚本确保原子性操作：只删除自己加的锁
            $script = <<<LUA
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
            LUA;

            $result = $redis->eval($script, [$lockKey, $lockValue], 1);
            if ($result) {
                unset(Coroutine::getContext()['redis_lock_' . $lockKey]);
            }
            return (bool)$result;
        });
    }

    /**
     * 在获取锁的情况下执行回调函数，执行完后自动释放锁
     *
     * 有时候进程锁感觉是没有用，可能是应用取得锁以后执行的操作太快了；
     * 取得锁->执行->释放锁；
     * 取得锁->执行->释放锁；
     * 取得锁->执行->释放锁；
     * 这个时候就不建议使用本方法，使用手动上锁，解锁 或者等待锁自动释放，
     *
     * @param string $lockKey 锁的键名
     * @param callable $callback 获取锁后要执行的回调函数
     * @param int $ttl 锁的超时时间（毫秒）
     * @param int $retryCount 获取锁失败时的重试次数
     * @param int $retryDelay 重试间隔（毫秒）
     * @return mixed 回调函数的返回值
     * @throws Throwable
     */
    public static function withLock(string $lockKey, callable $callback, int $ttl = 10000, int $retryCount = 3, int $retryDelay = 200): mixed
    {
        $locked = false;
        $attempts = 0;

        // 尝试获取锁，最多重试指定次数
        while (!$locked && $attempts < $retryCount) {
            $locked = self::lock($lockKey, $ttl);
            if (!$locked) {
                $attempts++;
                if ($attempts < $retryCount) {
                    // 等待一段时间后重试
                    Coroutine::sleep($retryDelay / 1000);
                }
            }
        }

        if (!$locked) {
            throw new RuntimeException("无法获取Redis锁: $lockKey");
        }

        try {
            // 执行回调函数
            return $callback();
        } finally {
            // 无论如何都要释放锁
            self::unlock($lockKey);
        }
    }


}