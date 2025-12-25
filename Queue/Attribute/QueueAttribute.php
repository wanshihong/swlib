<?php
declare(strict_types=1);

namespace Swlib\Queue\Attribute;

use Attribute;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Queue\MessageQueue;
use Throwable;

/**
 * 队列注解
 *
 * 标记一个方法通过队列异步执行。调用时不会立即执行方法体，而是将任务投递到队列，
 * 消费时才会真正执行方法逻辑。
 *
 * ## 执行流程
 *
 * 1. 调用方法 → 代理拦截 → 投递到队列 → 返回队列ID (int)
 * 2. 队列消费 → 执行原方法逻辑
 *
 * ## 返回值说明
 *
 * - 代理调用时：返回队列ID (int)，可用于取消队列任务
 * - 消费执行时：返回方法的实际执行结果
 *
 * **建议**：将方法返回类型声明为 `int`，并添加 `return 0;` 占位符，
 * 以便 IDE 正确推断代理调用时的返回类型。
 *
 * @example
 *
 * // 推荐写法：返回类型为 int，添加占位返回值
 * #[QueueAttribute(delay: 120, maxRetry: 1, clear: true)]
 * public static function delayedOffline(int $userId, int $appId): int
 * {
 *     // 业务逻辑（消费时执行）
 *     self::doOffline($userId, $appId);
 *
 *     return 0; // 占位符，代理调用时返回实际队列ID
 * }
 *
 * // 调用示例
 * $queueId = UserOnlineService::delayedOffline($userId, $appId);
 * // $queueId 是队列ID，可用于 MessageQueue::cancel($queueId) 取消任务
 *
 * // 队列执行方法支持以下可选参数（在执行时自动传入）：
 * // - MessageQueueTableDto $dto: 队列消息数据对象，包含消息的所有信息
 */
#[Attribute(Attribute::TARGET_METHOD)]
class QueueAttribute implements ProxyAttributeInterface
{

    /**
     * @param int $delay 延迟执行时间（秒），默认 0 表示立即执行
     * @param int $maxRetry 最大重试次数，默认 30
     * @param array $retryIntervals 重试时间间隔数组（秒），默认 [5, 10, 20, 30, 60, 90, 120, 180, 240, 300, 600, 1200, 1800, 3600]
     * @param bool $clear 入队时是否清理之前的同类消息，默认 false
     * @param int $priority 执行优先级，多个注解时需显式指定
     */
    public function __construct(
        public int   $delay = 0,
        public int   $maxRetry = 30,
        public array $retryIntervals = [5, 10, 20, 30, 60, 90, 120, 180, 240, 300, 600, 1200, 1800, 3600],
        public bool  $clear = false,
        public int   $priority = 0,
        public bool  $async = false
    )
    {

    }

    /**
     * @throws Throwable
     */
    public function handle(array $ctx, callable $next): int
    {
        $className = is_string($ctx['target']) ? $ctx['target'] : ($ctx['meta']['class'] ?? get_class($ctx['target']));
        // 队列投递后短路，返回队列 ID
        return MessageQueue::pushProxy([
            'class' => $className,
            'method' => $ctx['meta']['proxyMethod'],
            'arguments' => $ctx['arguments'],
            'isStatic' => $ctx['meta']['isStatic'],
            'config' => [
                'delay' => $this->delay,
                'maxRetry' => $this->maxRetry,
                'retryIntervals' => $this->retryIntervals,
                'clear' => $this->clear,
            ],
        ]);
    }
}

