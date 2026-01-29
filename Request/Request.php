<?php

namespace Swlib\Request;

use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swoole\Http\Request as SwooleRequest;

/**
 * URL 和请求相关工具类
 *
 * 提供 URL 处理、HTTPS 检测、Cookie 密钥生成等功能
 */
class Request
{
    /**
     * 获取当前请求的域名
     *
     * 会自动检测代理头部（x-forwarded-host）和原始 host 头部
     * 并根据协议类型自动添加 http:// 或 https:// 前缀
     *
     * 示例：
     * ```php
     * $host = UrlHelper::getHost();  // 返回：https://example.com
     * $host = UrlHelper::getHost($request);  // 使用指定的 Request 对象
     * ```
     *
     * @param SwooleRequest|null $request 请求对象，如果为 null 则从上下文获取
     * @return string 完整的域名 URL（包含协议）
     */
    public static function getHost(?SwooleRequest $request = null): string
    {
        $request = $request ?: CtxEnum::Request->get();
        $host = $request->header['x-forwarded-host'] ?? $request->header['host'] ?? 'unknown';

        if (str_starts_with($host, 'http')) {
            return $host;
        }
        $scheme = self::isHttps($request) ? 'https' : 'http';
        return "$scheme://$host";
    }

    /**
     * 判断当前请求是否 HTTPS
     *
     * 检查顺序：
     * 1. 配置中的 HTTPS 标志
     * 2. x-forwarded-proto 头部
     * 3. scheme 头部
     * 4. 默认返回 false
     *
     * 示例：
     * ```php
     * $isHttps = UrlHelper::isHttps();  // 返回：true 或 false
     * $isHttps = UrlHelper::isHttps($request);  // 使用指定的 Request 对象
     * ```
     *
     * @param SwooleRequest|null $request 请求对象，如果为 null 则从上下文获取
     * @return bool 是否为 HTTPS 请求
     */
    public static function isHttps(?SwooleRequest $request = null): bool
    {
        if (ConfigEnum::HTTPS) {
            return true;
        }

        $request = $request ?: CtxEnum::Request->get();

        // 检查 x-forwarded-proto 头部
        if (isset($request->header['x-forwarded-proto']) && $request->header['x-forwarded-proto'] === 'https') {
            return true;
        }

        // 检查 scheme 头部
        if (isset($request->header['scheme']) && $request->header['scheme'] === 'https') {
            return true;
        }

        // 默认返回 false
        return false;
    }

    /**
     * 获取 GET 参数
     *
     * @param string $key 参数名
     * @param string $errTip 错误提示信息
     * @param mixed $def 默认值，如果为 null 则参数必须存在
     * @return mixed 参数值
     * @throws AppException 当参数不存在且没有默认值时抛出异常
     */
    public static function get(string $key, string $errTip = '', mixed $def = null): mixed
    {
        $request = CtxEnum::Request->get();
        $get = $request->get ?: [];
        if (isset($get[$key])) {
            return $get[$key];
        }

        if ($def === null) {
            throw new AppException($errTip ?: AppErr::PARAM_ERROR);
        }
        return $def;
    }

    /**
     * 获取 POST 参数
     *
     * 支持 application/x-www-form-urlencoded 和 application/json 两种格式
     *
     * @param string $key 参数名
     * @param string $errTip 错误提示信息
     * @param mixed $def 默认值，如果为 null 则参数必须存在
     * @return mixed 参数值
     * @throws AppException 当参数不存在且没有默认值时抛出异常
     */
    public static function post(string $key, string $errTip = '', mixed $def = null): mixed
    {
        $request = CtxEnum::Request->get();
        $post = $request->post;
        if (empty($post)) {
            $post = json_decode($request->getContent(), true);
        }
        $post = $post ?: [];
        if (isset($post[$key])) {
            return $post[$key];
        }

        if ($def === null) {
            throw new AppException($errTip ?: AppErr::PARAM_ERROR);
        }
        return $def;
    }

    /**
     * 获取 Header 参数
     *
     * @param string $key 参数名
     * @param mixed $def 默认值
     * @return mixed 参数值或默认值
     */
    public static function getHeader(string $key, mixed $def = null): mixed
    {
        $request = CtxEnum::Request->get();
        $header = $request->header ?: [];
        if (isset($header[$key])) {
            return $request->header[$key];
        }

        return $def;
    }

}

