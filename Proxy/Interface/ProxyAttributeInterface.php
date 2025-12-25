<?php
declare(strict_types=1);

namespace Swlib\Proxy\Interface;


/**
 * 统一阶段接口，所有可参与编排的注解需实现。
 */
interface ProxyAttributeInterface
{
    /**
     * @var int 执行优先级 ， 数字越大优先级越高 ，数字大的先执行
     */
    public int $priority {
        get;
        set;
    }

    /**
     * 是否异步执行
     * 同步执行需要在 handle 中 调用 $next 保证执行不中断
     * 异步执行可以不用在   handle 中调用  $next 执行到此结束
     * @var bool
     */
    public bool $async {
        get;
        set;
    }

    /**
     * 阶段执行入口
     *
     * @param array $ctx 调度上下文
     * @param callable $next 下一个阶段
     */
    public function handle(array $ctx, callable $next): mixed;
}

