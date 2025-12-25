<?php
declare(strict_types=1);

namespace Swlib\TaskProcess\Attribute;

use Attribute;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\TaskProcess\TaskDispatcher;
use Throwable;

/**
 * Task 注解
 *
 * 标记一个方法可以通过 task 进程异步执行 异步执行了 原方法拿不到返回值，原方法返回值只能是 void
 * 编译时会生成 Task 类，通过静态方法触发 task 进程执行
 *
 * @example
 *
 * #[TaskAttribute]
 * public static function processData(int $userId, array $data): void
 * {
 *     // 业务逻辑，在 task 进程中执行
 *     // ...
 * }
 *
 * // 调用方式
 * Task::App_Common_Service_DataService_processData($userId, $data);
 */
#[Attribute(Attribute::TARGET_METHOD)]
class TaskAttribute implements ProxyAttributeInterface
{

    /**
     * @param string $name 可选的任务名称，用于标识任务，默认使用类名_方法名
     * @param int $timeout 任务执行超时时间（秒），默认 300 秒
     * @param int $priority 执行优先级，多个注解时需显式指定
     */
    public function __construct(
        public string $name = '',
        public int    $timeout = 300,
        public int    $priority = 0,
        public bool   $async = true
    )
    {

    }

    /**
     * @throws Throwable
     */
    public function handle(array $ctx, callable $next): null
    {
        $className = is_string($ctx['target']) ? $ctx['target'] : get_class($ctx['target']);
        TaskDispatcher::dispatch([
            'class' => $className,
            'method' => $ctx['meta']['proxyMethod'],
            'arguments' => $ctx['arguments'],
            'isStatic' => $ctx['meta']['isStatic'],
            'timeout' => $this->timeout,
            'name' => $this->name,
        ]);

        // task 投递后短路
        return null;
    }
}
