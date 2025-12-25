<?php

namespace Swlib\Admin\Config;

use Throwable;

interface AdminConfigInterface
{


    public function getAppId();

    /**
     * @throws Throwable
     */
    public function configAdminTitle();

    /**
     * @throws Throwable
     */
    public function configMenus();

}