<?php

namespace Swlib\Admin\Middleware;

use Swlib\Admin\Manager\AdminManager;
use Swlib\Response\ResponseInterface;
use Swlib\Router\RouterMiddleware;

class AdminInitMiddleware extends RouterMiddleware
{

    public function handle(): ResponseInterface|true
    {
        // 初始化后台
        AdminManager::getInstance();
        return true;
    }
}