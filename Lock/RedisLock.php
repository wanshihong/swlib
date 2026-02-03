<?php
declare(strict_types=1);

namespace Swlib\Lock;


use Redis;
use Swlib\Connect\PoolRedis;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Coroutine\CoroutineContext;
use Swlib\Exception\AppException;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

class RedisLock
{


    /**
     * 获取Redis排他锁
     *
     * @param string $lockKey 锁的键名
     * @param int $ttl 锁的超时时间（毫秒）
     * @return string|false 成功返回锁值，失败返回 false
     * @throws Throwable
     */
    public static function lock(string $lockKey, int $ttl = 10000): string|false
    {
        // 生成唯一的锁值
        $lockValue = uniqid('', true);

        $result = PoolRedis::call(function (Redis $redis) use ($lockKey, $ttl, $lockValue) {
            // 使用SET命令的NX和PX选项实现排他锁
            // NX: 只有当key不存在时才设置值
            // PX: 设置过期时间（毫秒）
            return $redis->set($lockKey, $lockValue, ['NX', 'PX' => $ttl]);
        });

        return $result ? $lockValue : false;
    }

    /**
     * 释放Redis排他锁
     *
     * @param string $lockKey 锁的键名
     * @param string $lockValue 锁值（必须传入加锁时返回的值）
     * @return bool 是否成功释放锁
     * @throws Throwable
     */
    public static function unlock(string $lockKey, string $lockValue): bool
    {
        $result = PoolRedis::call(function (Redis $redis) use ($lockKey, $lockValue) {
            // 使用Lua脚本确保原子性操作：只删除自己加的锁
            $script = <<<LUA
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
            LUA;

            return $redis->eval($script, [$lockKey, $lockValue], 1);
        });

        return (bool)$result;
    }

    /**
     * 续期锁（延长锁的过期时间）
     *
     * @param string $lockKey 锁的键名
     * @param string $lockValue 锁值（必须传入加锁时返回的值）
     * @param int $ttl 新的超时时间（毫秒）
     * @return bool 是否成功续期
     * @throws Throwable
     */
    public static function renew(string $lockKey, string $lockValue, int $ttl = 10000): bool
    {
        $result = PoolRedis::call(function (Redis $redis) use ($lockKey, $lockValue, $ttl) {
            // 使用Lua脚本确保原子性操作：只续期自己加的锁
            $script = <<<LUA
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('pexpire', KEYS[1], ARGV[2])
            else
                return 0
            end
            LUA;

            return $redis->eval($script, [$lockKey, $lockValue, $ttl], 1);
        });

        return (bool)$result;
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
     * @param callable $callback 获取锁后要执行的回调函数（会传入锁值作为参数）
     * @param int $ttl 锁的超时时间（毫秒）
     * @param int $retryCount 获取锁失败时的重试次数
     * @param int $retryDelay 重试间隔（毫秒）
     * @param bool $autoRenew 是否自动续期（当业务执行时间可能超过TTL时启用）
     * @return mixed 回调函数的返回值
     * @throws Throwable
     */
    public static function withLock(
        string $lockKey,
        callable $callback,
        int $ttl = 10000,
        int $retryCount = 3,
        int $retryDelay = 200,
        bool $autoRenew = false
    ): mixed {
        $lockValue = false;
        $attempts = 0;

        // 尝试获取锁，最多重试指定次数
        while (!$lockValue && $attempts < $retryCount) {
            $lockValue = self::lock($lockKey, $ttl);
            if (!$lockValue) {
                $attempts++;
                if ($attempts < $retryCount) {
                    // 等待一段时间后重试
                    if (CoroutineContext::inCoroutine()) {
                        Coroutine::sleep($retryDelay / 1000);
                    } else {
                        usleep($retryDelay * 1000);
                    }
                }
            }
        }

        if (!$lockValue) {
            // 无法获取Redis锁
            throw new AppException(LanguageEnum::LOCK_FAILED . ": $lockKey");
        }

        // 自动续期定时器ID
        $renewTimerId = null;

        try {
            // 如果启用自动续期，创建定时器
            if ($autoRenew) {
                // 在 TTL 的 2/3 时间时续期
                $renewInterval = (int)($ttl * 2 / 3);
                if ($renewInterval > 0) {
                    $renewTimerId = Timer::tick($renewInterval, function () use ($lockKey, $lockValue, $ttl) {
                        self::renew($lockKey, $lockValue, $ttl);
                    });
                }
            }

            // 执行回调函数，传入锁值
            return $callback($lockValue);
        } finally {
            // 清除续期定时器
            if ($renewTimerId !== null) {
                Timer::clear($renewTimerId);
            }

            // 无论如何都要释放锁
            self::unlock($lockKey, $lockValue);
        }
    }


}