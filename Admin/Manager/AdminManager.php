<?php

namespace Swlib\Admin\Manager;

use Generate\ConfigEnum;
use Swlib\Admin\Menu\Menu;
use Swlib\Admin\Menu\MenuGroup;
use Swlib\Controller\Language\Service\Language;
use Swlib\DataManager\WorkerSingleton;
use Swlib\Enum\CtxEnum;
use Throwable;

class AdminManager extends WorkerSingleton
{
    public string $title = '后台管理';
    public string $uploadUrl = '';
    public string $adminIndexUrl = '';

    // 退出登录路由，后台模板中有调用，再页面最右上角
    public string $logoutUrl = '';
    public string $loginUrl = '';

    // 退出登录路由，后台模板中有调用，再页面最右上角
    public string $changePasswordUrl = '';

    // 无权限路由
    public string $noAccessUrl = '';

    // 设置语言路由
    public string $setLanguageUrl = '';
    // 所有语言列表
    public array $languages = [];


    /**
     * @throws Throwable
     */
    protected function initialize(): void
    {
        $this->languages = Language::getLanguages();
        $adminNamespace = ConfigEnum::get('ADMIN_NAMESPACE');
        $className = $adminNamespace . '\AdminConfig';

        (new $className)->Init($this);
    }


    private function __clone()
    {

    }

    /**
     * 获取当前的菜单
     * @return MenuGroup[]|Menu[]
     */
    public function getMenus(): array
    {
        $adminNamespace = ConfigEnum::get('ADMIN_NAMESPACE');
        $className = $adminNamespace . '\AdminConfig';
        $menus = (new $className)->configMenus();

        // 遍历菜单，设置选中状态
        $isSelect = false;
        /** @var MenuGroup|Menu $menu */
        foreach ($menus as $menu) {
            $menu->checkActive();
            if ($menu->isActive) {
                $isSelect = true;
                break;
            }
        }

        if ($isSelect === false) {
            // 查找activeMatchWeight数值最小的菜单并设置为选中
            $minWeight = null;
            $activeMenu = null;

            foreach ($menus as $menu) {
                $weight = $menu->activeMatchWeight;
                if ($minWeight === null || $weight < $minWeight) {
                    $minWeight = $weight;
                    $activeMenu = $menu;

                    // 如果是完全匹配（checkActiveByFull选中的），直接选择，不需要继续遍历
                    if ($weight === 1) {
                        break;
                    }
                }
            }

            // 设置最小权重的菜单为选中状态
            if ($activeMenu !== null) {
                $activeMenu->isActive = true;
            }

        }

        return $menus;
    }

    /**
     * @throws Throwable
     */
    public function getTitle(): string
    {
        $adminNamespace = ConfigEnum::get('ADMIN_NAMESPACE');
        $className = $adminNamespace . '\AdminConfig';
        $adminTitle = (new $className)->configAdminTitle();
        return Language::get($adminTitle);
    }


    /**
     * 获取当前的语言
     * @return string
     */
    public function getLang(): string
    {
        return $this->languages[CtxEnum::Lang->get()] ?? '';
    }

}
