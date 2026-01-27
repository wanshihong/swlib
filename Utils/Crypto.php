<?php
declare(strict_types=1);

namespace Swlib\Utils;

/**
 * 加密工具类
 * 提供 HMAC-SHA256 签名算法，与前端保持一致
 */
class Crypto
{
    /**
     * 计算 HMAC-SHA256
     *
     * @param string $message 要签名的消息
     * @param string $key 密钥
     * @return string 十六进制格式的 HMAC 值
     */
    public static function hmacSha256(string $message, string $key): string
    {
        return hash_hmac('sha256', $message, $key);
    }

    /**
     * 生成 API 签名
     *
     * @param string $url API 路径
     * @param string $random 随机数
     * @param string $timestamp 时间戳
     * @param string $appSecret 应用密钥
     * @return string 签名字符串
     */
    public static function sign(string $url, string $random, string $timestamp, string $appSecret): string
    {
        $message = "$url.$random.$timestamp";
        return self::hmacSha256($message, $appSecret);
    }
}
