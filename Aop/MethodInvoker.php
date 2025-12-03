<?php
declare(strict_types=1);

namespace Swlib\Aop;

use Generate\ProxyMap;
use Swlib\Aop\Interface\AspectInterface;
use Swlib\Table\Db;
use Throwable;

/**
 * 基于 Attribute 的 AOP 调度器
 *
 * 静态编译后的包装方法会统一调用本类，例如：
 *
 *   return \Swlib\Aop\MethodInvoker::invoke($this, __FUNCTION__, func_get_args());
 *
 * 或静态方法：
 *
 *   return \Swlib\Aop\MethodInvoker::invoke(self::class, __FUNCTION__, func_get_args());
 */
final class MethodInvoker
{

    private static array $metaCache = [];

    /**
     * 调用被编织过的目标方法
     *
     * @param object|string $target 目标对象实例（普通方法）或类名（静态方法）
     * @param string $method 原方法名（未带 __inner 后缀）
     * @param array $arguments 实际调用参数
     * @param string $declaringClass 声明该方法/trait 的类名或 trait 名（用于静态切面映射）
     * @return mixed
     * @throws Throwable
     */
    public static function invoke(object|string $target, string $method, array $arguments, string $declaringClass): mixed
    {
        $class = is_object($target) ? get_class($target) : $target;

        // 从静态映射表中获取切面/事务配置
        $meta = self::getMeta($declaringClass, $method);

        // 计算实际调用的 inner 方法
        $innerMethod = $method . '__inner';
        $innerCallable = is_string($target)
            ? [$class, $innerMethod]
            : [$target, $innerMethod];


        // 提前返回优化：没有切面和事务时直接调用
        if (empty($meta) || (empty($meta['aspects']) && empty($meta['transaction']))
        ) {
            return $innerCallable(...$arguments);
        }

        $aspectInstances = [];
        $txArgs = null;

        if (isset($meta['aspects']) && $meta['aspects']) {
            foreach ($meta['aspects'] as $aspectMeta) {
                $className = $aspectMeta['class'] ?? null;
                $args = $aspectMeta['arguments'] ?? [];

                if (is_string($className)) {
                    /** @var class-string<AspectInterface> $className */
                    $aspectInstances[] = new $className(...$args);
                }
            }
        }

        if (isset($meta['transaction']) && $meta['transaction']) {
            $txArgs = $meta['transaction']['arguments'] ?? [];
        }


        // 构造 JoinPoint（方法名使用原方法名，便于日志等展示）
        $joinPoint = new JoinPoint($target, $method, $arguments);

        // 事务包装（如果存在 Transaction 注解）
        if ($txArgs !== null) {
            return Db::transaction(
                call: static fn() => self::executePipeline($aspectInstances, $innerCallable, $joinPoint),
                dbName: $txArgs['dbName'] ?? 'default',
                isolationLevel: $txArgs['isolationLevel'] ?? null,
                timeout: $txArgs['timeout'] ?? null,
                enableLog: $txArgs['logTransaction'] ?? false
            );
        }

        return self::executePipeline($aspectInstances, $innerCallable, $joinPoint);
    }

    private static function getMeta(string $declaringClass, string $method): ?array
    {
        $key = $declaringClass . '::' . $method;
        if (!isset(self::$metaCache[$key])) {
            self::$metaCache[$key] = ProxyMap::MAP[$declaringClass][$method] ?? null;
        }
        return self::$metaCache[$key];
    }


    /**
     * @throws Throwable
     */
    private static function executePipeline(
        array     $aspectInstances,
        callable  $innerCallable,
        JoinPoint $joinPoint
    ): mixed
    {
        // 没有切面直接调用 inner 方法
        if (empty($aspectInstances)) {
            return $innerCallable(...$joinPoint->arguments);
        }

        try {
            // 1. before
            foreach ($aspectInstances as $aspect) {
                $aspect->before($joinPoint);
            }

            // 2. around
            $aroundResult = null;
            foreach ($aspectInstances as $aspect) {
                $aroundResult = $aspect->around($joinPoint);
                if ($aroundResult !== null) {
                    break;
                }
            }

            // 3. 如果 around 没有返回结果则执行原方法
            $callArgs = $joinPoint->arguments;
            $result = $aroundResult !== null
                ? $aroundResult
                : $innerCallable(...$callArgs);

            // 4. after
            foreach ($aspectInstances as $aspect) {
                $aspect->after($joinPoint, $result);
            }

            return $result;
        } catch (Throwable $e) {
            // 5. afterThrowing
            foreach ($aspectInstances as $aspect) {
                $aspect->afterThrowing($joinPoint, $e);
            }
            throw $e;
        }
    }

}

