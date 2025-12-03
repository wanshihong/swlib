<?php
declare(strict_types=1);

namespace Swlib\Table\Aspects;

use Swlib\Aop\Abstract\AbstractAspect;
use Attribute;

use Swlib\Aop\JoinPoint;
use Swlib\Table\Interface\TableInterface;


/**
 * 数据库查询 清理
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class QueryCleanAspect extends AbstractAspect
{


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


}

