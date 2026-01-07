<?php

namespace Swlib\Utils;

use Exception;
use Swlib\App;

/**
 * 开发环境 SSL 证书管理
 *
 * 证书存放在用户目录下，多个项目可以共用同一份证书
 */
class DevSslCert
{
    /**
     * 证书存放目录（用户目录下的 .swlib-ssl）
     */
    public static function getSslDir(): string
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE') ?: '/tmp';
        return rtrim($homeDir, '/') . '/.swlib-ssl/';
    }

    public static function getCertFile(): string
    {
        return self::getSslDir() . 'cert.pem';
    }

    public static function getKeyFile(): string
    {
        return self::getSslDir() . 'key.pem';
    }

    public static function getIosCertFile(): string
    {
        return self::getSslDir() . 'cert_ios.cer';
    }

    /**
     * 生成开发环境 SSL 证书并返回配置
     * @throws Exception
     */
    public static function generateAndGetConfig(array $config): array
    {
        self::ensureCertificate();

        $config['ssl_cert_file'] = self::getCertFile();
        $config['ssl_key_file'] = self::getKeyFile();
        return $config;
    }

    /**
     * 确保证书存在且有效
     * @throws Exception
     */
    public static function ensureCertificate(): void
    {
        $sslSaveDir = self::getSslDir();
        $sslCertFile = self::getCertFile();
        $sslKeyFile = self::getKeyFile();
        $sslIosCertFile = self::getIosCertFile();

        $localIP = App::getLocalIP();
        $needRegenerate = false;

        // 检查证书文件是否存在
        if (!file_exists($sslCertFile) || !file_exists($sslKeyFile)) {
            $needRegenerate = true;
        } else {
            // 证书存在，检查证书的 CN 是否与当前 IP 匹配
            $certCN = self::getCertificateCN($sslCertFile);
            if ($certCN !== $localIP) {
                echo "SSL certificate CN ($certCN) does not match current IP ($localIP), regenerating...\n";
                // 删除旧证书文件
                @unlink($sslCertFile);
                @unlink($sslKeyFile);
                @unlink($sslIosCertFile);
                $needRegenerate = true;
            }
        }

        if ($needRegenerate) {
            // 确保 ssl 目录存在
            if (!is_dir($sslSaveDir)) {
                mkdir($sslSaveDir, 0700, true);
            }

            // 生成自签名证书
            $command = "openssl req -x509 -newkey rsa:2048 -keyout $sslKeyFile -out $sslCertFile -days 365 -nodes -subj '/CN=$localIP'";
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception("Failed to generate SSL certificate. \n 请手动执行 \n $command");
            }

            echo "SSL certificate generated successfully for IP: $localIP\n";
            echo "Certificate location: $sslSaveDir\n";

            // 生成提供给 iOS 安装的 DER 格式证书文件
            self::generateIosCert($sslCertFile, $sslIosCertFile);
        } elseif (!file_exists($sslIosCertFile) && file_exists($sslCertFile)) {
            // 如果已经存在 PEM 证书但缺少 iOS 证书文件，则根据现有证书补充生成
            self::generateIosCert($sslCertFile, $sslIosCertFile);
        }
    }

    /**
     * 生成 iOS 证书（DER 格式）
     * @throws Exception
     */
    private static function generateIosCert(string $certFile, string $iosCertFile): void
    {
        $iosCommand = "openssl x509 -in $certFile -outform der -out $iosCertFile";
        exec($iosCommand, $iosOutput, $iosReturnVar);

        if ($iosReturnVar !== 0) {
            throw new Exception("Failed to generate iOS SSL certificate. \n 请手动执行 \n $iosCommand");
        }
    }

    /**
     * 获取证书的 CN（Common Name）
     */
    public static function getCertificateCN(string $certFile): ?string
    {
        $command = "openssl x509 -in $certFile -noout -subject";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || empty($output)) {
            return null;
        }

        // 解析输出，格式通常为: subject=CN = 192.168.1.100 或 subject= /CN=192.168.1.100
        $subject = $output[0];
        if (preg_match('/CN\s*=\s*([^\s,\/]+)/', $subject, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

