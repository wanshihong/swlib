<?php

namespace Swlib\Lock;

use RuntimeException;
use Swlib\Coroutine\CoroutineContext;
use Swlib\DataManager\WorkerManager;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

/**
 * 进程级别的排他锁
 * 注意：此锁只在单个进程内有效，不能用于多进程间的锁
 *
 * 使用场景：
 * 1. 进程内多个协程竞争同一资源（文件、内存数据等）
 * 2. 防止同一进程内的重复执行（如定时任务）
 * 3. 进程内的临界区保护
 *
 * 锁机制：
 * - lock() 返回唯一的锁值（成功）或 false（失败）
 * - unlock() 必须传入锁值才能解锁，防止误解锁
 * - 锁值可以在协程间传递，实现灵活的锁管理
 */
class WorkerLock
{
    /**
     * 锁的前缀
     */
    private const string LOCK_PREFIX = 'worker_lock:';

    /**
     * 定时器前缀
     */
    private const string TIMER_PREFIX = 'worker_lock_timer:';

    /**
     * 获取锁
     *
     * @param string $lockKey 锁的键名
     * @param int $timeout 获取锁的超时时间（秒）
     * @param int $ttl 锁的有效期（秒），防止死锁
     * @return string|false 成功返回锁值，失败返回 false
     */
    public static function lock(string $lockKey, int $timeout = 3, int $ttl = 60): string|false
    {
        $key = self::getLockKey($lockKey);
        $startTime = microtime(true);
        $lockValue = uniqid(posix_getpid() . ':', true);

        // 尝试获取锁，直到超时
        while (true) {
            // 原子性地检查并设置锁
            $existingLock = WorkerManager::get($key);
            $now = time();

            // 如果锁不存在或已过期，尝试获取锁
            if (!$existingLock || (isset($existingLock['expire_time']) && $existingLock['expire_time'] < $now)) {
                // 设置锁（包含过期时间和锁值）
                $expireTime = $now + $ttl;
                WorkerManager::set($key, [
                    'value' => $lockValue,
                    'expire_time' => $expireTime,
                    'pid' => posix_getpid(),
                    'cid' => CoroutineContext::getCid(),
                ]);

                // 二次验证：确保是我们设置的锁（防止竞态条件）
                $verifyLock = WorkerManager::get($key);
                if (!$verifyLock || $verifyLock['value'] !== $lockValue) {
                    // 锁被其他协程抢占，继续重试
                    if (microtime(true) - $startTime >= $timeout) {
                        return false;
                    }
                    self::safeSleep(10); // 10ms
                    continue;
                }

                // 创建锁过期定时器
                self::createExpireTimer($lockKey, $lockValue, $ttl);

                return $lockValue;
            }

            // 检查是否超时
            if (microtime(true) - $startTime >= $timeout) {
                return false;
            }

            // 短暂休眠后重试
            self::safeSleep(10); // 10ms
        }
    }

    /**
     * 释放锁
     *
     * @param string $lockKey 锁的键名
     * @param string $lockValue 锁值（必须传入加锁时返回的值）
     * @return bool 是否成功释放锁
     */
    public static function unlock(string $lockKey, string $lockValue): bool
    {
        $key = self::getLockKey($lockKey);
        $existingLock = WorkerManager::get($key);

        // 验证锁值
        if ($existingLock && isset($existingLock['value']) && $existingLock['value'] === $lockValue) {
            // 清除定时器
            self::clearTimer($lockKey);

            // 删除锁
            WorkerManager::delete($key);

            return true;
        }

        return false;
    }


    /**
     * 续期锁（延长锁的过期时间）
     *
     * @param string $lockKey 锁的键名
     * @param string $lockValue 锁值（必须传入加锁时返回的值）
     * @param int $ttl 新的超时时间（秒）
     * @return bool 是否成功续期
     */
    public static function renew(string $lockKey, string $lockValue, int $ttl = 60): bool
    {
        $key = self::getLockKey($lockKey);
        $existingLock = WorkerManager::get($key);

        // 只续期自己加的锁
        if ($existingLock && isset($existingLock['value']) && $existingLock['value'] === $lockValue) {
            // 更新过期时间
            $existingLock['expire_time'] = time() + $ttl;
            WorkerManager::set($key, $existingLock);

            // 清除旧的定时器并创建新的
            self::clearTimer($lockKey);
            self::createExpireTimer($lockKey, $lockValue, $ttl);

            return true;
        }

        return false;
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
     * @param int $timeout 获取锁的超时时间（秒）
     * @param int $ttl 锁的有效期（秒）
     * @param int $retryCount 获取锁失败时的重试次数
     * @param int $retryDelay 重试间隔（毫秒）
     * @param bool $autoRenew 是否自动续期（当业务执行时间可能超过TTL时启用）
     * @return mixed 回调函数的返回值
     * @throws Throwable
     */
    public static function withLock(
        string $lockKey,
        callable $callback,
        int $timeout = 3,
        int $ttl = 60,
        int $retryCount = 3,
        int $retryDelay = 200,
        bool $autoRenew = false
    ): mixed {
        $lockValue = false;
        $attempts = 0;

        // 尝试获取锁，最多重试指定次数
        while (!$lockValue && $attempts < $retryCount) {
            $lockValue = self::lock($lockKey, $timeout, $ttl);
            if (!$lockValue) {
                $attempts++;
                if ($attempts < $retryCount) {
                    self::safeSleep($retryDelay);
                }
            }
        }

        if (!$lockValue) {
            throw new RuntimeException("无法获取进程锁: $lockKey ，已重试 $retryCount 次");
        }

        // 自动续期定时器ID
        $renewTimerId = null;

        try {
            // 如果启用自动续期，创建定时器
            if ($autoRenew) {
                // 在 TTL 的 2/3 时间时续期
                $renewInterval = (int)($ttl * 1000 * 2 / 3);
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




    /**
     * 协程安全的 sleep
     *
     * @param int $milliseconds 毫秒数
     * @return void
     */
    private static function safeSleep(int $milliseconds): void
    {
        if (CoroutineContext::inCoroutine()) {
            Coroutine::sleep($milliseconds / 1000);
        } else {
            usleep($milliseconds * 1000);
        }
    }

    /**
     * 创建锁过期定时器
     *
     * @param string $lockKey 锁的键名
     * @param string $lockValue 锁值
     * @param int $ttl 超时时间（秒）
     * @return void
     */
    private static function createExpireTimer(string $lockKey, string $lockValue, int $ttl): void
    {
        $key = self::getLockKey($lockKey);

        // 创建定时器，在锁到期时自动释放
        $timerId = Timer::after($ttl * 1000, function () use ($lockKey, $lockValue, $key) {
            // 检查锁是否仍然存在且是当前锁
            $existingLock = WorkerManager::get($key);
            if ($existingLock && isset($existingLock['value']) && $existingLock['value'] === $lockValue) {
                // 锁仍然存在且是当前锁，释放它
                WorkerManager::delete($key);

                // 清除定时器记录
                $timerKey = self::getTimerKey($lockKey);
                WorkerManager::delete($timerKey);
            }
        });

        // 存储定时器ID，以便后续可以清除
        $timerKey = self::getTimerKey($lockKey);
        WorkerManager::set($timerKey, [
            'timer_id' => $timerId,
            'lock_value' => $lockValue,
        ]);
    }

    /**
     * 清除定时器
     *
     * @param string $lockKey 锁的键名
     * @return void
     */
    private static function clearTimer(string $lockKey): void
    {
        $timerKey = self::getTimerKey($lockKey);
        $timerInfo = WorkerManager::get($timerKey);
        if ($timerInfo && isset($timerInfo['timer_id'])) {
            Timer::clear($timerInfo['timer_id']);
            WorkerManager::delete($timerKey);
        }
    }

    /**
     * 获取锁的完整键名
     *
     * @param string $lockKey 锁的键名
     * @return string 完整的锁键名
     */
    private static function getLockKey(string $lockKey): string
    {
        return self::LOCK_PREFIX . $lockKey;
    }

    /**
     * 获取定时器的完整键名
     *
     * @param string $lockKey 锁的键名
     * @return string 完整的定时器键名
     */
    private static function getTimerKey(string $lockKey): string
    {
        return self::TIMER_PREFIX . $lockKey;
    }
}