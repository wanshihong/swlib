<?php
declare(strict_types=1);

namespace Swlib\DataManager;

use ReflectionClass;
use ReflectionException;

/**
 * 反射类进程级别缓存管理器
 *
 * 用于缓存 ReflectionClass 实例，避免频繁创建反射对象
 * 使用 WorkerManager 实现进程级别的缓存
 *
 * 使用示例：
 * $reflectionClass = ReflectionManager::getClass(MyController::class);
 * $reflectionClass = ReflectionManager::getClassFromObject($controllerInstance);
 */
class ReflectionManager
{
    /**
     * 缓存键前缀
     */
    private const string CACHE_PREFIX = 'reflection:';

    /**
     * 获取类的反射实例（通过类名）
     *
     * @param object|string $class
     * @return ReflectionClass
     * @throws ReflectionException
     */
    public static function getClass(object|string $class): ReflectionClass
    {
        if (is_object($class)) {
            $className = get_class($class);
        } else {
            $className = $class;
        }

        $cacheKey = self::CACHE_PREFIX . $className;

        return WorkerManager::getSet($cacheKey, function () use ($className) {
            return new ReflectionClass($className);
        });
    }


    /**
     * 清除指定类的反射缓存
     *
     * @param string $className 类名
     */
    public static function clearClass(string $className): void
    {
        $cacheKey = self::CACHE_PREFIX . $className;
        WorkerManager::delete($cacheKey);
    }

    /**
     * 清除所有反射缓存
     * 注意：这会清除 WorkerManager 中所有以 reflection: 开头的缓存
     */
    public static function clearAll(): void
    {
        // 遍历 WorkerManager 的 pool，删除所有反射缓存
        foreach (WorkerManager::$pool as $key => $value) {
            if (str_starts_with($key, self::CACHE_PREFIX)) {
                unset(WorkerManager::$pool[$key]);
            }
        }
    }
}

