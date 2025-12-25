<?php

namespace Swlib\Admin\Config;

use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Menu\Menu;
use Swlib\Admin\Menu\MenuGroup;

use Throwable;


abstract  class AdminConfigAbstract
{

    public function Init(AdminManager $layout): void
    {

    }

    /**
     * @throws Throwable
     */
    public function configAdminTitle(): string
    {
        return '管理后台';
    }

    /**
     * @return MenuGroup[]|Menu[]
     * @throws Throwable
     */
    public function configMenus(): array
    {
        return [];
    }

}