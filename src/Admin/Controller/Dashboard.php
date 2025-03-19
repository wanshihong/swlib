<?php

namespace Swlib\Admin\Controller;

use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Middleware\AdminInitMiddleware;
use Swlib\Admin\Middleware\PermissionsMiddleware;
use Swlib\Controller\AbstractController;
use Swlib\Response\TwigResponse;
use Swlib\Router\Router;
use Swlib\Utils\Func;
use Swlib\Utils\Language;
use Throwable;

#[Router(middleware: AdminInitMiddleware::class)]
class Dashboard extends AbstractController
{


    #[Router(method: 'GET',middleware:  [AdminInitMiddleware::class, PermissionsMiddleware::class])]
    public function index(): TwigResponse
    {
        $host = Func::getHost();
        return TwigResponse::render("index.twig", [
            'host' => $host
        ]);
    }


    /**
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function noAccess(): TwigResponse
    {
        return TwigResponse::render("noAccess.twig", [
            'msg' => Language::get('您没有权限访问该页面！'),
            'loginText' => Language::get('返回登录'),
            'loginUrl'=> AdminManager::getInstance()->loginUrl
        ]);
    }

}