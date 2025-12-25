<?php
declare(strict_types=1);

namespace Swlib\Admin\Action\Attribute;

use Attribute;
use Swlib\Admin\Manager\AdminUserManager;

/**
 * 批量操作注解类
 *
 * 用于在控制器方法上添加批量操作配置，批量操作会显示在列表页面的分页区域左侧
 *
 * 使用示例：
 * #[BatchAction(label: "批量删除", confirmMessage: "确定要删除选中的数据吗？")]
 * public function batchDelete(): JsonResponse {
 *     $ids = $this->post('ids'); // 获取选中的主键ID数组
 *     // 执行批量删除逻辑
 *     return JsonResponse::success();
 * }
 *
 * #[BatchAction(label: "批量启用", confirmMessage: "确定要启用选中的数据吗？")]
 * public function batchEnable(): JsonResponse {
 *     $ids = $this->post('ids');
 *     // 执行批量启用逻辑
 *     return JsonResponse::success();
 * }
 *
 * #[BatchAction(label: "自定义操作", jsFiles: ["/admin/js/custom-batch.js"], cssFiles: ["/admin/css/custom-batch.css"])]
 * public function batchCustom(): JsonResponse {
 *     // 自定义批量操作逻辑
 *     return JsonResponse::success();
 * }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class BatchAction
{
    /**
     * 构造函数
     *
     * @param string $label 批量操作显示的文本标签（显示在下拉菜单中）
     * @param string $url 批量操作的URL
     * @param array $params 批量操作的参数，支持占位符语法：以 % 开头的值会被替换为当前行对应字段的值
     *                      例如：params: [PostContentTable::POST_ID => '%' . PostsTable::ID]
     *                      运行时 '%id' 会被替换为当前行的 id 字段值
     * @param string $confirmMessage 确认对话框的提示消息
     * @param string $icon 操作图标，使用Bootstrap Icons，如 "bi bi-trash"
     * @param int $sort 操作排序，数字越小越靠前
     * @param bool $enable 是否启用此批量操作
     * @param array $allowRoles 允许使用此操作的角色列表
     * @param array $jsFiles 需要加载的JS文件列表
     * @param array $cssFiles 需要加载的CSS文件列表
     */
    public function __construct(
        public string $label,
        public string $url = '',//批量操作的URL
        public array  $params = [],//批量操作的 参数
        public string $confirmMessage = '确定要执行此操作吗？',
        public string $icon = '',
        public int    $sort = 0,
        public bool   $enable = true,
        public array  $allowRoles = [AdminUserManager::DEFAULT_LOGIN_ROLE],
        public array  $jsFiles = [],
        public array  $cssFiles = [],
    )
    {
        $this->jsFiles[] = '/admin/js/batch-action.js';
    }
}

