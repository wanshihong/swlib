<?php

namespace Swlib\Admin\Menu;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Controller\Language\Service\Language;
use Throwable;

class MenuGroup implements PermissionInterface
{

    use PermissionTrait;

    public int $activeMatchWeight = 999;

    public bool $isActive = false;

    /**
     * @var Menu[]
     */
    public array $menus = [];


    /**
     * @throws Throwable
     */
    public function __construct(public string $label, public string $icon = '')
    {
        $this->label = Language::get($label);
    }


    public function setMenus(Menu ...$menus): static
    {
        $this->menus = $menus;
        return $this;
    }

    public function checkActive(): void
    {
        $this->isActive = false;

        // 重置所有子菜单的选中状态
        foreach ($this->menus as $menu) {
            $menu->isActive = false;
        }
        // 第一优先级：完全匹配（路径+所有参数）
        foreach ($this->menus as $menu) {
            $menu->checkActiveByFull();
            if ($menu->isActive) {
                $this->activeMatchWeight = 1;
                $menu->isActive = true;
                return; // 找到完全匹配就立即返回，不再检查其他匹配方式
            }
        }

        // 第二优先级：路径匹配 + 包含所需参数（允许有额外参数）
        foreach ($this->menus as $menu) {
            $menu->checkActiveByPathParams();
            if ($menu->isActive) {
                $this->activeMatchWeight = 2;
                return; // 找到参数匹配就立即返回
            }
        }

        // 第三优先级：仅路径匹配（最宽松）
        foreach ($this->menus as $menu) {
            $menu->checkActiveByPath();
            if ($menu->isActive) {
                $this->activeMatchWeight = 3;
                return; // 找到路径匹配就立即返回
            }
        }
    }

}