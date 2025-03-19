<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;

class Func
{

    /**
     * 获取当前请求的域名
     * @return string
     */
    public static function getHost(): string
    {
        $request = CtxEnum::Request->get();
        $host = $request->header['host'];
        $scheme = Ip::isLocal($host) ? 'https' : (self::isHttps() ? 'https' : 'http');
        return "$scheme://$host";
    }


    /**
     * 判断当前请求是否 https
     */
    public static function isHttps(): bool
    {
        $request = CtxEnum::Request->get();

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

    public static function getCookieKey(string $key): string
    {
        return $key . ":" . ConfigEnum::PROJECT_UNIQUE;
    }


    /**
     * 转大驼峰，并且首字母大写
     * @param string $string
     * @param string $separator
     * @return array|string|null
     */
    public static function underscoreToCamelCase(string $string, string $separator = "_"): array|string|null
    {
        $words = explode($separator, $string);
        $result = array_map('ucfirst', $words);
        return implode('', $result);
    }


}