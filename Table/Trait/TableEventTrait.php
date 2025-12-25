<?php
declare(strict_types=1);

namespace Swlib\Table\Trait;

use Swlib\Table\Operation\DatabaseOperationEnum;

/**
 * 每个 Table 统一引入的“表级事件” Trait
 *
 * 注意：真正的事件名常量由代码生成器在具体 Table 类中生成，
 * 这里只提供基于 DatabaseOperationEnum + before/after 的辅助方法。
 */
trait TableEventTrait
{

    /**
     * 根据操作类型和阶段，返回当前表对应的事件名
     *
     * 约定：具体 Table 必须定义好对应的常量，例如：
     *  - self::SelectBefore / self::SelectAfter
     *  - self::InsertBefore / self::InsertAfter
     *  - self::UpdateBefore / self::UpdateAfter
     *  - self::DeleteBefore / self::DeleteAfter
     */
    public static function getOperationEventName(DatabaseOperationEnum $operation, bool $before): ?string
    {
        return match ($operation) {
            DatabaseOperationEnum::INSERT => $before ? self::InsertBefore : self::InsertAfter,
            DatabaseOperationEnum::UPDATE => $before ? self::UpdateBefore : self::UpdateAfter,
            DatabaseOperationEnum::DELETE => $before ? self::DeleteBefore : self::DeleteAfter,
            DatabaseOperationEnum::SELECT => $before ? self::SelectBefore : self::SelectAfter,
        };
    }
}
