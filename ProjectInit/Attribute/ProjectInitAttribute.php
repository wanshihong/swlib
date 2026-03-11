<?php
declare(strict_types=1);

namespace Swlib\ProjectInit\Attribute;

use Attribute;

/**
 * 项目启动前的最后阶段执行一次的初始化注解。
 *
 * 适用于配置建档、配置预热、静态注册等一次性初始化逻辑。
 * 执行时机在 Swoole Server start() 前，不要依赖 Swoole 运行期能力，
 * 例如 worker 生命周期、连接上下文、task、定时器、运行期协程调度等。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ProjectInitAttribute
{
    public function __construct(
        public string $desc = '',
        public string $method = 'handle',
    ) {
    }
}
