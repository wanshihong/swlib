<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Cookie
{
    /**
     * 设置 Cookie
     *
     * @param string $key Cookie 的键名
     * @param mixed $value Cookie 的值
     * @param int $expire Cookie 过期时间戳，0 表示浏览器关闭时过期
     * @param string $path Cookie 的路径，默认为 '/'
     * @param string $domain Cookie 的域名，默认为空
     * @param bool $secure 是否仅在 HTTPS 连接时发送，默认为 false
     * @param bool $httpOnly 是否仅通过 HTTP 协议访问，默认为 false
     * @param string $sameSite SameSite 属性，可选值：'Strict', 'Lax', 'None'，默认为空
     * @return void
     */
    public static function set(
        string $key,
        mixed  $value,
        int    $expire = 0,
        string $path = '/',
        string $domain = '',
        bool   $secure = false,
        bool   $httpOnly = false,
        string $sameSite = ''
    ): void
    {
        /** @var Response $response */
        $response = CtxEnum::Response->get();
        $cookieKey = self::getKey($key);

        // 使用 cookie 方法设置 Cookie
        $response->cookie(
            $cookieKey,
            (string)$value,
            time() + $expire,
            $path,
            $domain,
            $secure,
            $httpOnly,
            $sameSite
        );
    }

    /**
     * 获取 Cookie 值
     *
     * @param string $key Cookie 的键名
     * @param mixed $default 如果 Cookie 不存在时返回的默认值
     * @return mixed Cookie 的值或默认值
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var Request $request */
        $request = CtxEnum::Request->get();
        $cookieKey = self::getKey($key);

        return $request->cookie[$cookieKey] ?? $default;
    }

    /**
     * 删除 Cookie（通过设置过期时间为过去的时间）
     *
     * @param string $key Cookie 的键名
     * @param string $path Cookie 的路径，默认为 '/'
     * @param string $domain Cookie 的域名，默认为空
     * @return void
     */
    public static function delete(string $key, string $path = '/', string $domain = ''): void
    {
        self::set($key, '', time() - 1, $path, $domain);
    }

    /**
     * 检查 Cookie 是否存在
     *
     * @param string $key Cookie 的键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        /** @var Request $request */
        $request = CtxEnum::Request->get();
        $cookieKey = self::getKey($key);

        return isset($request->cookie[$cookieKey]);
    }

    /**
     * 生成带有项目唯一标识的 Cookie 密钥
     *
     * 用于为 Cookie 名称添加项目特定的前缀，避免不同项目间的 Cookie 冲突
     *
     * 示例：
     * ```php
     * $cookieKey = Cookie::getKey('session');  // 返回：session:project_unique_id
     * ```
     *
     * @param string $key Cookie 的基础密钥名称
     * @return string 带有项目唯一标识的 Cookie 密钥
     */
    public static function getKey(string $key): string
    {
        return $key . ":" . ConfigEnum::PROJECT_UNIQUE;
    }
}