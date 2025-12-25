<?php
declare(strict_types=1);

namespace Swlib\Admin\Controller\Attribute;

use Attribute;
use ReflectionException;
use Swlib\Admin\Controller\Enum\AdminActionEnum;
use Swlib\Admin\Controller\Interface\AdminControllerInterface;
use Swlib\DataManager\ReflectionManager;

/**
 * 禁用操作注解类
 *
 * 用于在控制器类上标记需要禁用的方法，
 * 被标记的方法将不会显示操作按钮，且用户无法访问该方法。
 *
 * 使用示例：
 *
 * // 禁用单个方法
 * #[DisableAction(actions: [AdminActionEnum::DELETE])]
 * class MyController extends AbstractAdmin {
 *     // delete 方法将被禁用
 * }
 *
 * // 禁用多个方法
 * #[DisableAction(actions: [AdminActionEnum::NEW, AdminActionEnum::DELETE])]
 * class MyController extends AbstractAdmin {
 *     // new 和 delete 方法将被禁用
 * }
 *
 * // 自定义禁用消息
 * #[DisableAction(actions: [AdminActionEnum::DELETE], message: "该功能已被管理员禁用")]
 * class MyController extends AbstractAdmin {
 *     // delete 方法将被禁用，显示自定义消息
 * }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DisableAction
{

    /**
     * @var string[] 被禁用的方法列表
     */
    public array $actions;

    /**
     * @var string|null 自定义禁用消息，为 null 时使用枚举的默认消息
     */
    public ?string $message;

    /**
     * 构造函数
     *
     * @param AdminActionEnum[] $actions 需要禁用的方法枚举数组
     * @param string|null $message 自定义禁用消息，为 null 时使用枚举的默认消息
     */
    public function __construct(
        array   $actions = [],
        ?string $message = null
    )
    {
        /** @var AdminActionEnum|string $action */
        foreach ($actions as $action) {
            if ($action instanceof AdminActionEnum) {
                $this->actions[] = $action->value;
            } else {
                $this->actions[] = $action;
            }
        }

        $this->message = $message;
    }

    /**
     * 检查指定方法是否被禁用
     *
     * @param AdminActionEnum|string $methodName 方法名称
     * @return bool
     */
    public function isActionDisabled(AdminActionEnum|string $methodName): bool
    {
        if ($methodName instanceof AdminActionEnum) {
            $methodName = $methodName->value;
        }

        // 如果禁用了 DELETE，同时也禁用 BATCH_DELETE
        if ($methodName === AdminActionEnum::BATCH_DELETE->value) {
            if (array_any($this->actions, fn($action) => $action === AdminActionEnum::DELETE->value)) {
                return true;
            }
        }

        return array_any($this->actions, fn($action) => $action === $methodName);
    }


    /**
     * 检查指定类的方法是否被禁用，并返回禁用状态和消息
     *
     * @param AdminControllerInterface $adminController
     * @param AdminActionEnum|string $methodName 方法名称
     * @return bool
     * @throws ReflectionException
     */
    public static function checkMethodDisabled(AdminControllerInterface $adminController, AdminActionEnum|string $methodName): bool
    {
        $reflectionClass = ReflectionManager::getClass($adminController);

        $disableAttributes = $reflectionClass->getAttributes(DisableAction::class);
        if (empty($disableAttributes)) {
            return false;
        }

        /** @var DisableAction $disableAction */
        $disableAction = $disableAttributes[0]->newInstance();


        return $disableAction->isActionDisabled($methodName);

    }
}

