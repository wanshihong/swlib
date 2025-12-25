<?php
declare(strict_types=1);

namespace Swlib\Table\Aspects;

use Swlib\Aop\Abstract\AbstractAspect;
use Attribute;

use Swlib\Aop\JoinPoint;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Table\Interface\TableInterface;


/**
 * 数据库查询 清理
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class QueryCleanAspect extends AbstractAspect implements ProxyAttributeInterface
{
    public function __construct(
        public int  $priority = 0,
        public bool $async = false
    )
    {
    }

    /**
     * 后置通知 - 记录返回值
     *
     * @param JoinPoint $joinPoint
     * @param mixed $result
     * @return void
     */
    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        /** @var TableInterface $target */
        $target = $joinPoint->target;
        $target->queryClean();
    }


    public function handle(array $ctx, callable $next): mixed
    {
        $joinPoint = new JoinPoint($ctx['target'], $ctx['meta']['method'], $ctx['arguments']);
        return $this->around($joinPoint, static fn() => $next($ctx));
    }
}

