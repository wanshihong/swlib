<?php

namespace Swlib\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
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


    /**
     * 获取IP地址信息,ip 归属地
     * @throws AppException
     */
    public static function getLocation($ip): array
    {
        $client = new Client();

        // 尝试使用百度API获取IP信息
        $data = self::fetchIpInfo($client, "https://qifu-api.baidubce.com/ip/geo/v1/district?ip=$ip", 'code', 'Success');
        if ($data && isset($data['prov'], $data['city'], $data['isp'])) {
            return self::formatIpInfo(
                ip: $ip,
                region: $data['prov'],
                city: $data['city'],
                isp: $data['isp'],
                country: $data['country'] ?? '本地',
                county: $data['district'] ?? ''
            );
        }

        // 尝试使用ip-api.com获取IP信息
        $data = self::fetchIpInfo($client, "http://ip-api.com/json/$ip?lang=zh-CN", 'status', 'success');
        if ($data) {
            return self::formatIpInfo(
                ip: $ip,
                region: $data['regionName'] ?? '本地',
                city: $data['city'] ?? '本地',
                isp: $data['isp'] ?? '本地',
                country: $data['country'] ?? '本地',
                county: $data['county'] ?? ''
            );
        }

        // 默认返回本地信息
        return self::formatIpInfo(
            ip: $ip,
            region: '本地',
            city: '本地',
            isp: '本地',
            country: '本地',
            county: ''
        );
    }

    /**
     * 从指定URL获取IP信息
     * @throws AppException
     */
    private static function fetchIpInfo(Client $client, string $url, string $statusKey, string $successValue): ?array
    {
        try {
            $response = $client->request('GET', $url);
            if ($response->getStatusCode() != 200) {
                throw new AppException("请求异常 error code %d", $response->getStatusCode());
            }
            $data = json_decode($response->getBody()->getContents(), true);
            return ($data[$statusKey] === $successValue) ? $data : null;
        } catch (GuzzleException $e) {
            throw new AppException("API请求失败: " . $e->getMessage());
        }
    }

    /**
     * 格式化IP信息
     */
    private static function formatIpInfo(string $ip, string $region, string $city, string $isp, string $country, string $county): array
    {
        return [
            'ip' => $ip,
            'region' => $region,
            'isp' => $isp,
            'city' => $city,
            'country' => $country,
            'county' => $county,
        ];
    }

}