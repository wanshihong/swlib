<?php

namespace Swlib\Utils;


use Swlib\Enum\CtxEnum;
use Swoole\Http\Request;

class Ip
{
    public static function get(): string
    {
        /** @var Request $request */
        $request = CtxEnum::Request->get();


        if (isset($request->header['x-real-ip'])) {
            $ip = $request->header['x-real-ip'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }


        if (isset($request->header['x-forwarded-for'])) {
            $ipString = $request->header['x-forwarded-for'];
            $ipArr = explode(',', $ipString);
            foreach ($ipArr as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        if (isset($request->server['remote_addr'])) {
            $ip = $request->server['remote_addr'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'unknown';
    }


    public static function isLocal($ip): bool
    {
        $arr = explode(':', $ip);
        $ip = $arr[0];
        $ipLong = ip2long($ip);

        // 检查 IPv4 回环地址
        if ($ipLong === ip2long('127.0.0.1')) {
            return true;
        }

        // 检查 IPv4 私有地址范围
        if (
            ($ipLong >= ip2long('10.0.0.0') && $ipLong <= ip2long('10.255.255.255')) ||
            ($ipLong >= ip2long('172.16.0.0') && $ipLong <= ip2long('172.31.255.255')) ||
            ($ipLong >= ip2long('192.168.0.0') && $ipLong <= ip2long('192.168.255.255'))
        ) {
            return true;
        }

        // 检查 IPv6 回环地址
        if ($ip === '::1') {
            return true;
        }

        return false;
    }

}