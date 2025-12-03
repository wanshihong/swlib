<?php
declare(strict_types=1);

namespace Swlib\Aop\Interface;

use Swlib\Aop\JoinPoint;
use Throwable;

/**
 * 切面接口
 * 
 * 定义切面的标准契约，包含四种通知类型
 */
interface AspectInterface
{
    /**
     * 前置通知 - 在方法执行前调用
     *
     * @param JoinPoint $joinPoint 连接点，包含方法执行的上下文信息
     * @return void
     */
    public function before(JoinPoint $joinPoint): void;

    /**
     * 后置通知 - 在方法成功执行后调用
     *
     * @param JoinPoint $joinPoint 连接点
     * @param mixed $result 方法的返回值
     * @return void
     */
    public function after(JoinPoint $joinPoint, mixed $result): void;

    /**
     * 异常通知 - 在方法抛出异常时调用
     *
     * @param JoinPoint $joinPoint 连接点
     * @param Throwable $exception 抛出的异常
     * @return void
     */
    public function afterThrowing(JoinPoint $joinPoint, Throwable $exception): void;

    /**
     * 环绕通知 - 完全控制方法的执行
     * 
     * 返回 null 表示继续执行原方法
     * 返回其他值将替代原方法的返回值
     *
     * @param JoinPoint $joinPoint 连接点
     * @return mixed|null 返回 null 继续执行原方法，否则替代原方法返回值
     */
    public function around(JoinPoint $joinPoint): mixed;
}

