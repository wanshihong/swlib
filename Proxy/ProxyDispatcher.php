<?php
declare(strict_types=1);

namespace Swlib\Proxy;

use Generate\CallChainMap;
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;
use Throwable;

/**
 * 阶段式代理调度器
 *
 * 执行流程（AOP 标准三阶段）：
 * 1. Phase 1: 所有 AOP 注解的 before() [按 priority 降序执行]
 * 2. Phase 2: 所有注解的 handle() pipeline [按 priority 降序]
 * 3. Phase 3: 所有 AOP 注解的 after() [按 priority 降序执行]
 * 4. 异常时: 所有 AOP 注解的 afterThrowing() [按 priority 降序执行]
 *
 * 链路信息收集：
 * - 调用 ProxyContext::pop() 获取最近一次调度的链路信息
 * - 调用 ProxyContext::current() 获取当前调度的链路信息（不弹出）
 */
final class ProxyDispatcher
{
    /**
     * 调度执行代理方法
     *
     * @param int $chainKey 常量 KEY（数字索引）
     * @param object|string $target 目标对象实例（普通方法）或类名（静态方法）
     * @param array $arguments 实际调用参数
     * @return mixed
     * @throws Throwable
     */
    public static function dispatch(
        int           $chainKey,
        object|string $target,
        array         $arguments
    ): mixed
    {
        $chain = CallChainMap::CHAINS[$chainKey];
        $stages = $chain['stages'] ?? [];

        // 压入新的调度上下文
        $proxyResult = ProxyContext::push();

        $ctx = [
            'target' => $target,
            'arguments' => $arguments,
            'meta' => $chain,
            'proxyResult' => $proxyResult, // 传递给各注解使用
        ];

        // 实例化所有注解，并分离 AOP 切面
        $stageInstances = [];
        $stageClasses = []; // 记录每个 stage 的类名
        $aopAspects = []; // [['instance' => AbstractAspect, 'joinPoint' => JoinPoint], ...]

        foreach ($stages as $stageMeta) {
            $stageClass = $stageMeta['class'];
            $args = $stageMeta['arguments'] ?? [];
            $instance = new $stageClass(...$args);
            $stageInstances[] = $instance;
            $stageClasses[] = $stageClass;

            // 如果是 AOP 切面，创建 JoinPoint 并记录
            if ($instance instanceof AbstractAspect) {
                $joinPoint = new JoinPoint($target, $chain['method'], $arguments);
                $aopAspects[] = [
                    'instance' => $instance,
                    'joinPoint' => $joinPoint,
                ];
            }
        }

        try {
            // ========== Phase 1: 执行所有 AOP 的 before() ==========
            foreach ($aopAspects as $aop) {
                $aop['instance']->before($aop['joinPoint']);
            }

            // ========== Phase 2: 构建并执行 handle() pipeline ==========
            // 最终调用节点 - 记录真实返回值
            $terminal = static function (array $ctx) use ($proxyResult) {
                $result = self::invokeMethod(
                    $ctx['target'],
                    $ctx['meta']['proxyMethod'],
                    $ctx['arguments'],
                    $ctx['meta']['isStatic']
                );
                $proxyResult->setResult($result);
                return $result;
            };

            // 从后向前构建 pipeline（stages 已按 priority 降序排列）
            $runner = $terminal;
            for ($i = count($stageInstances) - 1; $i >= 0; --$i) {
                $stage = $stageInstances[$i];
                $stageClass = $stageClasses[$i];
                $next = $runner;
                $runner = static function (array $ctx) use ($next, $stage, $stageClass, $proxyResult) {
                    $nextCalled = false;
                    $wrappedNext = static function (array $ctxParam) use ($next, &$nextCalled) {
                        $nextCalled = true;
                        return $next($ctxParam);
                    };

                    $result = $stage->handle($ctx, $wrappedNext);

                    // 记录该注解的执行结果
                    $proxyResult->setProxyResult($stageClass, $result);

                    // 如果没有调用 $next，标记为短路
                    if (!$nextCalled) {
                        $proxyResult->markShortCircuited($stageClass);
                    }

                    return $result;
                };
            }

            $result = $runner($ctx);

            // ========== Phase 3: 执行所有 AOP 的 after() ==========
            foreach ($aopAspects as $aop) {
                $aop['instance']->after($aop['joinPoint'], $result);
            }

            return $result;

        } catch (Throwable $e) {
            // ========== 异常处理: 执行所有 AOP 的 afterThrowing() ==========
            foreach ($aopAspects as $aop) {
                $aop['instance']->afterThrowing($aop['joinPoint'], $e);
            }
            throw $e;
        }
    }

    /**
     * 统一方法调用器
     */
    public static function invokeMethod(
        object|string $target,
        string        $method,
        array         $arguments,
        bool          $isStatic
    ): mixed
    {
        if ($isStatic) {
            $class = is_string($target) ? $target : get_class($target);
            return $class::$method(...$arguments);
        }

        if (is_string($target)) {
            $target = new $target();
        }

        return $target->$method(...$arguments);
    }
}

