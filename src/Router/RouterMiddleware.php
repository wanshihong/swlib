<?php

namespace Swlib\Router;

use Swlib\Response\ResponseInterface;


/**
 *  返回 true 执行以后的逻辑
 *  返回 ResponseInterface 路由执行结束，返回数据给前台
 *  抛出异常，路由执行结束,路由执行会捕获异常，返回 JsonResponse error 错误给前台
 */
abstract class RouterMiddleware
{

    abstract public function handle(): ResponseInterface|true;
}