<?php
declare(strict_types=1);

namespace Swlib\Admin\Action\Attribute;

use Attribute;
use Swlib\Admin\Action\Enum\ActionPosEnum;
use Swlib\Admin\Manager\AdminUserManager;

/**
 * 操作按钮注解类
 *
 * 用于在控制器方法上添加操作按钮配置
 *
 * 使用示例：
 * #[ActionButton(label: "编辑", url: "edit", showOn: ["index-lists", "detail"], icon: "bi bi-pencil")]
 * public function edit(): TwigResponse {
 *     // 业务逻辑
 * }
 *
 * #[ActionButton(label: "添加", url: "new", showOn: ["index-add"], icon: "bi bi-plus", sort: 0)]
 * public function new(): TwigResponse {
 *     // 业务逻辑
 * }
 *
 * #[ActionButton(label: "自定义操作", url: "custom", showOn: ["index-lists"], icon: "bi bi-star", sort: 10)]
 * public function custom(): JsonResponse {
 *     // 业务逻辑
 * }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ActionButton
{


    /**
     * 构造函数
     *
     * @param string $label 按钮显示的文本标签
     * @param ActionPosEnum[] $showOn 按钮显示的位置，可选值：INDEX, LIST, FORM_NEW, FORM_EDIT, DETAIL
     * @param string $icon 按钮图标，使用Bootstrap Icons，如 "bi bi-pencil"
     * @param int $sort 按钮排序，数字越大越靠后
     * @param string $target 点击按钮后的跳转方式，默认_self，可选 _blank, _parent, _top
     * @param string $template 按钮使用的模板文件
     * @param array $params 按钮附加的参数，支持占位符语法：以 % 开头的值会被替换为当前行对应字段的值
     *                      例如：params: [PostContentTable::POST_ID => '%' . PostsTable::ID]
     *                      运行时 '%id' 会被替换为当前行的 id 字段值
     * @param string $sourceUrl 链接的来源页面URL，用于锁定左侧导航菜单
     */
    public function __construct(
        public string $label,
        public string $url = '', //按钮点击后跳转的URL或路由名称,在收集器中收集
        public array  $params = [],
        public array  $showOn = [], //显示在哪些位置
        public string $icon = '',
        public int    $sort = 0,
        public string $target = '_self',
        public string $template = 'action/action-alink.twig',
        public string $sourceUrl = '',
        public bool   $enable = true,
        public array  $allowRoles = [AdminUserManager::DEFAULT_LOGIN_ROLE],
        public array  $cssFiles = [],
        public array  $jsFiles = [],
    )
    {
    }

}
