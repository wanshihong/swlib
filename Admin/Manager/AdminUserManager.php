<?php

namespace Swlib\Admin\Manager;

use Generate\Tables\Main\AdminManagerTable;
use Generate\TablesDto\Main\AdminManagerTableDto;
use Redis;
use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Connect\PoolRedis;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Response\RedirectResponse;
use Swlib\Utils\Cookie;
use Swlib\Utils\Log;
use Throwable;

class AdminUserManager
{

    // 需要有这个权限才可以登录
    const array DEFAULT_LOGIN_ROLE = ['ROLE_ADMIN', 'ROLE_SUPPER_ADMIN'];

    const string ADMIN_SESSION_KEY = 'admin_session';

    /**
     * 返回当前登录的用户
     * @throws Throwable
     */
    public static function getUser()
    {
        return CtxEnum::Data->getSetData('admin-user', function () {
            $token = Cookie::get('admin_token');
            if (empty($token)) {
                throw new AppException("请登录");
            }
            $user = PoolRedis::call(function (Redis $redis) use ($token) {
                $key = "admin_user:$token";
                $user = $redis->hGetAll($key);
                $redis->expire($key, 3600);

                if (empty($user)) {
                    return null;
                }
                $dto = new AdminManagerTableDto();
                $dto->fromArray($user);
                return $dto;
            });

            if ($user) {
                return $user;
            }


            $userId = PoolRedis::call(function (Redis $redis) use ($token) {
                $redis->expire(AdminUserManager::ADMIN_SESSION_KEY, 3600);
                return $redis->hGet(AdminUserManager::ADMIN_SESSION_KEY, $token);
            });

            if (empty($userId)) {
                throw new AppException("请登录");
            }

            $find = new AdminManagerTable()->addWhere(AdminManagerTable::ID, $userId)->selectOne();
            if (empty($find)) {
                throw new AppException("请登录");
            }

            PoolRedis::call(function (Redis $redis) use ($token, $find) {
                $key = "admin_user:$token";
                $redis->hMSet($key, $find->toArray());
                $redis->expire($key, 3600);
            });

            return $find;
        });

    }

    /**
     * 获取当前用户的角色
     * @throws Throwable
     */
    public static function getRoles(): array
    {
        $user = self::getUser();
        return json_decode($user->roles, true);
    }


    /**
     * 判断当前用户是否拥有某角色
     * @throws Throwable
     */
    public static function hasRoles(string|array $role): bool
    {
        if (!is_array($role)) {
            $role = [$role];
        }

        $roles = self::getRoles();
        return array_any($role, fn($item) => in_array($item, $roles));

    }

    /**
     * 判断当前用户是否拥有某个权限
     * @throws Throwable
     */
    public static function hasPermissions(string|array $roles): bool
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        // 平铺一下数组，防止用户传入二维数组
        $roles = array_merge(...array_map(fn($item) => is_array($item) ? $item : [$item], $roles));

        $userRoles = self::getRoles();

        // 判断用户是否有权限
        // 需要的角色存在于用户角色中，则返回true
        if (array_any($userRoles, fn($userRole) => in_array($userRole, $roles))) {
            return true;
        }

        return false;

    }

    /**
     * 判断当前用户是否具有查看后台的基础权限
     * 没有这个权限，其他的权限都无效
     * @return RedirectResponse|true
     */
    public static function hasShowAdminPermissions(): RedirectResponse|true
    {
        // 判断是否有权限
        try {
            if (self::hasPermissions(self::DEFAULT_LOGIN_ROLE) === false) {
                // 没有权限则重定向到无权限页面
                return RedirectResponse::url(AdminManager::getInstance()->noAccessUrl);
            }
        } catch (Throwable $e) {
            // 如果过程中有异常，则可能是用户登录状态失效，则重定向到登录页面
            Log::saveException($e);
            $request = CtxEnum::Request->get();

            $queryString = $request->server['query_string'] ?? '';
            $retUrl = urlencode($request->server['path_info'] . ($queryString ? '?' . $queryString : ''));
            return RedirectResponse::url(AdminManager::getInstance()->loginUrl . "?ret-url=$retUrl");
        }
        return true;
    }


    /**
     * @throws Throwable
     */
    public static function checkPermissionsByConfig(PermissionInterface $config): bool
    {
        $roles = $config->roles;
        if (empty($roles)) {
            return true;
        }
        if (AdminUserManager::hasPermissions($roles) === true) {
            return true;
        }
        return false;
    }

}