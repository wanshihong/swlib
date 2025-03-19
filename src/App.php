<?php
declare(strict_types=1);

namespace Swlib;

use Exception;
use Generate\ConfigEnum;
use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Parse\ParseEvent;
use Swlib\Parse\TableBack;
use Swlib\Parse\ParseAdminConfig;
use Swlib\Parse\ParseConfig;
use Swlib\Parse\ParseLanguage;
use Swlib\Parse\ParseProcess;
use Swlib\Parse\ParseRouter;
use Swlib\Parse\ParseTable;
use Swlib\Utils\File;
use Swoole\WebSocket\Server;

define('SWLIB_DIR', __DIR__ . DIRECTORY_SEPARATOR);

class App
{

    public function __construct()
    {
        // 创建运行目录
        array_map(function ($path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }, [PUBLIC_DIR, RUNTIME_DIR, RUNTIME_DIR . 'log/']);

    }


    public function generateDevSSL(array $config)
    {
        if (ConfigEnum::APP_PROD === true) {
            return;
        }
        // 此功能较为简易，请勿在公网环境直接使用
        $config['document_root'] = PUBLIC_DIR;// v4.4.0以下版本, 此处必须为绝对路径
        // 开启静态文件请求处理功能，需配合 document_root 使用 默认 false
        $config['enable_static_handler'] = true;


        // 证书文件路径
        $sslSaveDir = RUNTIME_DIR . 'ssl/';
        $sslCertFile = $sslSaveDir . 'cert.pem';
        $sslKeyFile = $sslSaveDir . 'key.pem';

        // 检查证书文件是否存在
        if (!file_exists($sslCertFile) || !file_exists($sslKeyFile)) {
            // 确保 ssl 目录存在
            if (!is_dir($sslSaveDir)) {
                mkdir($sslSaveDir, 0777, true);
            }

            // 生成自签名证书
            $command = "openssl req -x509 -newkey rsa:2048 -keyout $sslKeyFile -out $sslCertFile -days 365 -nodes -subj '/CN=localhost'";
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception("Failed to generate SSL certificate. \n 请手动执行 \n $command");
            }

            echo "SSL certificate generated successfully.\n";
        }

        // 配置 Swoole 服务器
        $config['ssl_cert_file'] = $sslCertFile;
        $config['ssl_key_file'] = $sslKeyFile;
        return $config;
    }


    /**
     * @throws Exception
     */
    public function parse(): void
    {
        //解析过了，不需要再次解析
        if (is_file(RUNTIME_DIR . 'parse.lock')) {
            return;
        }

        echo "开始解析配置..." . PHP_EOL;
        $startTime = microtime(true);

        // 删除模板缓存文件,模板文件在开发环境会自动生成，
        // 在生产环境会有缓存，所以每次生成文件之前删除
        echo "正在删除模板缓存文件..." . PHP_EOL;
        $stepStartTime = microtime(true);
        File::delDirectory(RUNTIME_DIR . 'twig');
        echo "删除模板缓存文件完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析配置文件
        echo "正在解析配置文件..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseConfig();
        echo "解析配置文件完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析后台配置
        echo "正在解析后台配置..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseAdminConfig();
        echo "解析后台配置完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析表格字段
        echo "正在解析表格字段..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseTable();
        echo "解析表格字段完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析项目路由
        echo "正在解析项目路由..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseRouter();
        echo "解析项目路由完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析语言文件
        echo "正在解析语言文件..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseLanguage();
        echo "解析语言文件完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析自定义进程
        echo "正在解析自定义进程..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseProcess();
        echo "解析自定义进程完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 解析事件管理器
        echo "正在解析事件管理器..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new ParseEvent();
        echo "解析事件管理器完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 备份数据库
        echo "正在备份数据库..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new TableBack();
        echo "备份数据库完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 移动静态文件
        echo "正在移动静态文件..." . PHP_EOL;
        $stepStartTime = microtime(true);
        File::copyDirectory(SWLIB_DIR . '/Admin/static', PUBLIC_DIR . '/admin');
        echo "移动静态文件完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        echo "所有解析操作完成，总耗时: " . round(microtime(true) - $startTime, 4) . "秒" . PHP_EOL;

        file_put_contents(RUNTIME_DIR . 'parse.lock', 1);

        PoolMysql::close();
        PoolRedis::close();
    }


}

