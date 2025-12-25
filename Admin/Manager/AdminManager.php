<?php

namespace Swlib\Admin\Manager;

use Generate\AdminConfigMap;
use Swlib\Admin\Menu\MenuGroup;
use Swlib\DataManager\WorkerSingleton;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Language;
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

        $config = AdminConfigMap::Init;
        $className = $config[0];
        $methodName = $config[1];
        (new $className)->$methodName($this);
    }


    private function __clone()
    {

    }

    /**
     * 获取当前的菜单
     * @return MenuGroup[]
     */
    public function getMenus(): array
    {
        $config = AdminConfigMap::ConfigMenus;
        $className = $config[0];
        $methodName = $config[1];
        $menus = (new $className)->$methodName();


        // 遍历菜单，设置选中状态
        $isSelect = false;
        /** @var MenuGroup $menuGroup */
        foreach ($menus as $menuGroup) {
            $menuGroup->checkActive();
            if ($menuGroup->isActive) {
                $isSelect = true;
                break;
            }
        }

        if ($isSelect === false) {
            // 查找activeMatchWeight数值最小的菜单并设置为选中
            $minWeight = null;
            $activeMenuGroup = null;

            foreach ($menus as $menuGroup) {
                $weight = $menuGroup->activeMatchWeight;
                if ($minWeight === null || $weight < $minWeight) {
                    $minWeight = $weight;
                    $activeMenuGroup = $menuGroup;

                    // 如果是完全匹配（checkActiveByFull选中的），直接选择，不需要继续遍历
                    if ($weight === 1) {
                        break;
                    }
                }
            }

            // 设置最小权重的菜单为选中状态
            if ($activeMenuGroup !== null) {
                $activeMenuGroup->isActive = true;
            }

        }

        return $menus;
    }

    /**
     * @throws Throwable
     */
    public function getTitle(): string
    {
        $config = AdminConfigMap::ConfigTitle;
        $className = $config[0];
        $methodName = $config[1];
        $adminTitle = (new $className)->$methodName();
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