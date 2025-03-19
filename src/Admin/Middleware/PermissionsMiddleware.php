<?php

namespace Swlib\Admin\Middleware;

use Swlib\Admin\Manager\AdminUserManager;
use Swlib\Response\ResponseInterface;
use Swlib\Router\RouterMiddleware;

class PermissionsMiddleware extends RouterMiddleware
{

    public function handle(): ResponseInterface|true
    {
        // 判断是否有查看后台的基础权限
        return AdminUserManager::hasShowAdminPermissions();
    }
}