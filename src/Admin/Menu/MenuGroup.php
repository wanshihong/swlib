<?php

namespace Swlib\Admin\Menu;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Utils\Language;
use Throwable;

class MenuGroup implements PermissionInterface
{

    use PermissionTrait;

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

        foreach ($this->menus as $menu) {
            // 遍历子菜单，设置选中状态
            $menu->checkActiveByFull();
            if ($menu->isActive) {
                // 如果子菜单选择了，则本菜单组也选中
                $this->isActive = true;
                break;
            }
        }


        if (!$this->isActive) {
            foreach ($this->menus as $menu) {
                // 遍历子菜单，设置选中状态
                $menu->checkActiveByPathParams();
                if ($menu->isActive) {
                    // 如果子菜单选择了，则本菜单组也选中
                    $this->isActive = true;
                    break;
                }
            }
        }

        if (!$this->isActive) {
            foreach ($this->menus as $menu) {
                // 遍历子菜单，设置选中状态
                $menu->checkActiveByPath();
                if ($menu->isActive) {
                    // 如果子菜单选择了，则本菜单组也选中
                    $this->isActive = true;
                    break;
                }
            }
        }


    }

}