<?php

namespace Swlib\Admin\Controller;

use Generate\Tables\Main\AdminManagerTable;
use Redis;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\Admin\Middleware\AdminInitMiddleware;
use Swlib\Connect\PoolRedis;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Controller\Language\Service\Language;
use Swlib\Exception\AppException;
use Swlib\Request\Request;
use Swlib\Response\JsonResponse;
use Swlib\Response\RedirectResponse;
use Swlib\Response\TwigResponse;
use Swlib\Router\Router;
use Swlib\Utils\Cookie;
use Swlib\Utils\Url;
use Throwable;

#[Router(middleware: AdminInitMiddleware::class)]
class LoginAdmin extends AbstractController
{
    /**
     * @throws Throwable
     */
    #[Router(method: ["GET", "POST"])]
    public function login(): TwigResponse|JsonResponse
    {
        $method = $this->request->getMethod();
        if ($method === 'GET') {
            $retUrl = Request::get('ret-url', '', '');
            return TwigResponse::render("login/login.twig", [
                "title" => Language::get('登录'),
                "type" => "login",
                'successMsg' => Language::get('登录成功'),
                "successToUrl" => $retUrl ? urlencode($retUrl) : AdminManager::getInstance()->adminIndexUrl
            ]);
        } else {
            $username = Request::post("username", '请输入用户名');
            $password = Request::post("password", '请输入密码');

            $find = new AdminManagerTable()->addWhere(AdminManagerTable::USERNAME, $username)->selectOne();
            if (empty($find)) {
                // 用户名或者密码错误
                throw new AppException(LanguageEnum::ADMIN_USERNAME_PASSWORD_ERROR);
            }

            if (password_verify($password, $find->password) === false) {
                // 用户名或者密码错误
                throw new AppException(LanguageEnum::ADMIN_USERNAME_PASSWORD_ERROR);
            }


            $token = hash('sha256', Request::getHost() . $username . $password . time());

            PoolRedis::call(function (Redis $redis) use ($token, $find) {
                $redis->hSet(AdminUserManager::ADMIN_SESSION_KEY, $token, $find->id);
            });

            // 这里的 path 不要去掉，否则前台请求不携带 cookie
            Cookie::set('admin_token', $token, 86400 * 7);
            return JsonResponse::success();
        }
    }

    /**
     * @throws Throwable
     */
    #[Router(method: ["GET", "POST"])]
    public function changePassword(): TwigResponse|JsonResponse
    {
        $method = $this->request->getMethod();
        if ($method === 'GET') {
            return TwigResponse::render("login/login.twig", [
                "title" => Language::get('修改密码'),
                "type" => "changePassword",
                'successMsg' => Language::get('修改成功'),
                "successToUrl" => AdminManager::getInstance()->loginUrl
            ]);
        } else {
            $password = $this->post("password", '请输入密码');
            $password2 = $this->post("password2", '请确认密码');

            if ($password !== $password2) {
                // 两次密码不一致
                throw new AppException(LanguageEnum::ADMIN_PASSWORD_INCONSISTENT);
            }

            $find = AdminUserManager::getUser();

            $pwd = password_hash($password, PASSWORD_DEFAULT);
            new AdminManagerTable()->addWhere(AdminManagerTable::ID, $find->id)->update([
                AdminManagerTable::PASSWORD => $pwd,
            ]);

            Cookie::delete('admin_token');
            return JsonResponse::success();
        }

    }

    /**
     * @throws Throwable
     */
    #[Router(method: ['GET', 'POST'])]
    public function register(): TwigResponse|JsonResponse
    {
        $method = $this->request->getMethod();
        if ($method === 'GET') {
            return TwigResponse::render("login/login.twig", [
                "title" => Language::get('注册'),
                "type" => "register",
                'successMsg' => Language::get('注册成功'),
                "successToUrl" => AdminManager::getInstance()->loginUrl
            ]);
        } else {
            $username = Request::post("username", '请输入用户名');
            $password = Request::post("password", '请输入密码');
            $password2 = Request::post("password2", '请确认密码');

            if ($password !== $password2) {
                // 两次密码不一致
                throw new AppException(LanguageEnum::ADMIN_PASSWORD_INCONSISTENT);
            }


            $find = new AdminManagerTable()->addWhere(AdminManagerTable::USERNAME, $username)->selectOne();
            if ($find) {
                // 用户名已存在
                throw new AppException(LanguageEnum::ADMIN_USERNAME_EXISTS);
            }

            $pwd = password_hash($password, PASSWORD_DEFAULT);
            new AdminManagerTable()->insert([
                AdminManagerTable::USERNAME => $username,
                AdminManagerTable::PASSWORD => $pwd,
                AdminManagerTable::ROLES => '[]',
            ]);

            return JsonResponse::success();
        }

    }


    /**
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function logout(): RedirectResponse
    {
        $cookieKey = Cookie::getKey('admin_token');
        $token = $this->request->cookie[$cookieKey] ?? null;
        if (!empty($token)) {
            // 删除 redis 中的 token
            PoolRedis::call(function (Redis $redis) use ($token) {
                $redis->hDel(AdminUserManager::ADMIN_SESSION_KEY, $token);
            });
        }
        // 设置 cookie 过期
        Cookie::delete('admin_token');
        return RedirectResponse::url(Url::generateUrl('login'));
    }

}