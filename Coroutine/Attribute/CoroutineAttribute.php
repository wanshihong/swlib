<?php
declare(strict_types=1);

namespace Swlib\Coroutine\Attribute;

use Attribute;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Proxy\ProxyDispatcher;
use Swoole\Coroutine;

/**
 * 协程注解
 *
 * 标记一个方法可以通过协程异步执行, 异步执行了 原方法拿不到返回值，原方法返回值只能是 void
 * 编译时会生成 CoroutineRun 类，通过静态方法触发协程执行
 *
 * @example
 *
 * #[CoroutineAttribute]
 * public static function processData(int $userId, array $data): void
 * {
 *     // 协程中异步执行的逻辑
 *     // 这里可以进行耗时操作，不会阻塞主进程
 * }
 *
 * // 调用方式
 * CoroutineRun::LivePostCommentsApi_updateCategoryComment($table);
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CoroutineAttribute implements ProxyAttributeInterface
{

    /**
     * @param string $name 可选的协程方法名称，用于标识，默认使用 类名_方法名
     * @param int $priority 执行优先级，多个注解时需显式指定
     */
    public function __construct(
        public string $name = '',
        public int    $priority = 0,
        public bool   $async = true
    )
    {
    }

    public function handle(array $ctx, callable $next): null
    {
        Coroutine::create(static function () use ($ctx) {
            ProxyDispatcher::invokeMethod(
                $ctx['target'],
                $ctx['meta']['proxyMethod'],
                $ctx['arguments'],
                $ctx['meta']['isStatic']
            );
        });

        // 协程投递后短路
        return null;
    }
}
