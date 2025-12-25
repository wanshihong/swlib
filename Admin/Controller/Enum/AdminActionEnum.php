<?php
declare(strict_types=1);

namespace Swlib\Admin\Controller\Enum;

/**
 * 后台管理控制器操作枚举
 *
 * 定义 AbstractAdmin 中所有可被禁用的公开方法
 *
 * 使用示例：
 * #[DisableAction(actions: [AdminActionEnum::NEW, AdminActionEnum::DELETE])]
 * class MyController extends AbstractAdmin {
 *     // new 和 delete 方法将被禁用
 * }
 */
enum AdminActionEnum: string
{
    /**
     * 列表页面
     */
    case LISTS = 'lists';

    /**
     * 新建页面/操作
     */
    case NEW = 'new';

    /**
     * 编辑页面/操作
     */
    case EDIT = 'edit';

    /**
     * 删除操作
     */
    case DELETE = 'delete';

    /**
     * 详情页面
     */
    case DETAIL = 'detail';

    /**
     * 开关切换操作
     */
    case SWITCH = 'switch';

    /**
     * 获取选择列表
     */
    case GET_SELECT_LIST = 'getSelectList';


    /**
     * 批量删除
     */
    case BATCH_DELETE = 'batchDelete';


    /**
     * 获取枚举对应的默认禁用消息
     *
     * @return string
     */
    public function getDefaultMessage(): string
    {
        return match ($this) {
            self::LISTS => '列表功能已被禁用',
            self::NEW => '添加功能已被禁用',
            self::EDIT => '编辑功能已被禁用',
            self::DELETE => '删除功能已被禁用',
            self::DETAIL => '详情功能已被禁用',
            self::SWITCH => '开关功能已被禁用',
            self::GET_SELECT_LIST => '选择列表功能已被禁用',
            self::BATCH_DELETE => '批量删除功能已被禁用',
        };
    }
}

