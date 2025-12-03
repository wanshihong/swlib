<?php

namespace Swlib\Utils\Lock;

use RuntimeException;
use Swlib\DataManager\WorkerManager;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

/**
 * 进程级别的排他锁
 * 注意：此锁只在单个进程内有效，不能用于多进程间的锁
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
     * @return bool 是否成功获取锁
     */
    public static function lock(string $lockKey, int $timeout = 3, int $ttl = 60): bool
    {
        $key = self::getLockKey($lockKey);
        $startTime = time();
        $lockValue = uniqid(posix_getpid() . ':', true);

        // 尝试获取锁，直到超时
        while (true) {
            // 检查锁是否存在
            $existingLock = WorkerManager::get($key);

            // 如果锁不存在或已过期，尝试获取锁
            if (!$existingLock || (isset($existingLock['expire_time']) && $existingLock['expire_time'] < time())) {
                // 设置锁
                $expireTime = time() + $ttl;
                WorkerManager::set($key, [
                    'value' => $lockValue,
                    'expire_time' => $expireTime,
                ]);

                // 存储锁值到协程上下文，用于后续解锁验证
                Coroutine::getContext()["worker_lock_$lockKey"] = $lockValue;

                // 创建一个定时器，在锁到期时自动释放
                $timerId = Timer::tick($ttl * 1000, function () use ($lockKey, $lockValue, $key) {
                    // 检查锁是否仍然存在且是当前锁
                    $existingLock = WorkerManager::get($key);
                    if ($existingLock && isset($existingLock['value']) && $existingLock['value'] === $lockValue) {
                        // 锁仍然存在且是当前锁，释放它
                        WorkerManager::delete($key);

                        // 清除协程上下文中的锁值（如果当前协程还存在）
                        $context = Coroutine::getContext();
                        if (isset($context["worker_lock_$lockKey"])) {
                            unset($context["worker_lock_$lockKey"]);
                        }

                        // 清除定时器
                        self::clearTimer($lockKey);
                    }
                });

                // 存储定时器ID，以便后续可以清除
                $timerKey = self::getTimerKey($lockKey);
                WorkerManager::set($timerKey, [
                    'timer_id' => $timerId,
                    'lock_value' => $lockValue,
                ]);

                return true;
            }

            // 检查是否超时
            if (time() - $startTime >= $timeout) {
                return false;
            }

            // 短暂休眠后重试
            usleep(10000); // 10ms
        }
    }

    /**
     * 释放锁
     *
     * @param string $lockKey 锁的键名
     * @return bool 是否成功释放锁
     */
    public static function unlock(string $lockKey): bool
    {
        $key = self::getLockKey($lockKey);
        $lockValue = Coroutine::getContext()["worker_lock_$lockKey"] ?? null;

        if (!$lockValue) {
            return false; // 没有找到锁值，可能不是当前协程加的锁
        }

        $existingLock = WorkerManager::get($key);

        // 只释放自己加的锁
        if ($existingLock && isset($existingLock['value']) && $existingLock['value'] === $lockValue) {
            // 清除定时器
            self::clearTimer($lockKey);

            // 删除锁
            WorkerManager::delete($key);
            unset(Coroutine::getContext()["worker_lock_$lockKey"]);
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
     * @param callable $callback 获取锁后要执行的回调函数
     * @param int $timeout 获取锁的超时时间（秒）
     * @param int $ttl 锁的有效期（秒）
     * @param int $retryCount 获取锁失败时的重试次数
     * @param int $retryDelay 重试间隔（毫秒）
     * @return mixed 回调函数的返回值
     * @throws Throwable
     */
    public static function withLock(string $lockKey, callable $callback, int $timeout = 3, int $ttl = 60, int $retryCount = 3, int $retryDelay = 200): mixed
    {
        $locked = false;
        $attempts = 0;

        // 尝试获取锁，最多重试指定次数
        while (!$locked && $attempts < $retryCount) {
            $locked = self::lock($lockKey, $timeout, $ttl);
            if (!$locked) {
                $attempts++;
                if ($attempts < $retryCount) {
                    // 等待一段时间后重试
                    usleep($retryDelay * 1000);
                }
            }
        }

        if (!$locked) {
            throw new RuntimeException("无法获取进程锁: $lockKey");
        }

        try {
            // 执行回调函数
            return $callback();
        } finally {
            // 无论如何都要释放锁
            self::unlock($lockKey);
        }
    }


    private static function clearTimer($lockKey): void
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