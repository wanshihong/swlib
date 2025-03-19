<?php
declare(strict_types=1);

namespace Swlib;

use Exception;
use Generate\ConfigEnum;
use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Parse\ParseEvent;
use Swlib\Parse\TableBack;
use Swlib\Process\Process;
use Swlib\ServerEvents\OnCloseEvent;
use Swlib\ServerEvents\OnFinishEvent;
use Swlib\ServerEvents\OnMessageEvent;
use Swlib\ServerEvents\OnOpenEvent;
use Swlib\ServerEvents\OnPipeMessageEvent;
use Swlib\ServerEvents\OnReceiveEvent;
use Swlib\ServerEvents\OnRequestEvent;
use Swlib\ServerEvents\OnStartEvent;
use Swlib\ServerEvents\OnTaskEvent;
use Swlib\ServerEvents\OnWorkerStartEvent;
use Swlib\ServerEvents\OnWorkerStopEvent;
use Swlib\Parse\ParseAdminConfig;
use Swlib\Parse\ParseConfig;
use Swlib\Parse\ParseLanguage;
use Swlib\Parse\ParseProcess;
use Swlib\Parse\ParseRouter;
use Swlib\Parse\ParseTable;
use Swlib\Utils\File;
use Swoole\WebSocket\Server;


// 项目根目录
define('SWLIB_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(SWLIB_DIR) . DIRECTORY_SEPARATOR);
define('APP_DIR', ROOT_DIR . 'App' . DIRECTORY_SEPARATOR);
define('PUBLIC_DIR', ROOT_DIR . 'public' . DIRECTORY_SEPARATOR);
define('RUNTIME_DIR', ROOT_DIR . 'runtime' . DIRECTORY_SEPARATOR);

// 运行环境
define('APP_ENV_DEV', 'dev');
define('APP_ENV_PROD', 'prod');

// 创建运行目录
foreach ([
             PUBLIC_DIR,
             RUNTIME_DIR,
             RUNTIME_DIR . 'log/'
         ] as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}


class App
{


    /**
     * @throws Exception
     */
    public function run($env): void
    {

        $this->parse($env);

        $config = [
            'hook_flags' => SWOOLE_HOOK_ALL,
            'daemonize' => $env === APP_ENV_PROD, // 设为true则以守护进程方式运行
            'worker_num' => ConfigEnum::WORKER_NUM,
            'task_worker_num' => ConfigEnum::TASK_WORKER_NUM,
            'task_enable_coroutine' => true,
            'task_max_request' => 1024,
            'upload_max_filesize' => 100 * 1024 * 1024,
            'heartbeat_idle_time' => 600, // 表示一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
            'heartbeat_check_interval' => 60,  // 表示每60秒遍历一次
            'enable_coroutine' => true,
            'max_request' => 1024,
            'dispatch_mode' => 4,// 根据IP 分配 worker进程
            'max_wait_time' => 10,
            'reload_async' => true,// 平滑重启
            'log_file' => RUNTIME_DIR . '/log/server_error.log'
        ];

        if ($env !== APP_ENV_PROD) {
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

            // 启动 Swoole 服务器
            $server = new Server("0.0.0.0", ConfigEnum::PORT, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);

        } else {
            $server = new Server("0.0.0.0", ConfigEnum::PORT, SWOOLE_PROCESS);

        }


        $server->set($config);

        // 绑定服务器事件
        $this->bindEvent($server);

        // 添加自定义进程
        Process::run($server);


        $port = ConfigEnum::PORT;


        $http = $env !== APP_ENV_PROD ? 'https' : 'http';
        echo "Swoole http server is started at $http://127.0.0.1:$port" . PHP_EOL;

        $server->start();

    }

    /**
     * @throws Exception
     */
    private function parse($env): void
    {
        // 生产环境，解析过了，不需要再次解析
        if ($env === APP_ENV_PROD && is_file(RUNTIME_DIR . 'parse.lock')) {
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
        new ParseConfig($env);
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

        if ($env === APP_ENV_PROD) {
            file_put_contents(RUNTIME_DIR . 'parse.lock', 1);
        }

        PoolMysql::close();
        PoolRedis::close();
    }

    private function bindEvent($server): void
    {
        $server->on('receive', [new OnReceiveEvent(), 'handle']);
        $server->on('start', [new OnStartEvent(), 'handle']);
        $server->on('workerStart', [new OnWorkerStartEvent(), 'handle']);
        $server->on('workerStop', [new OnWorkerStopEvent(), 'handle']);
        $server->on('pipeMessage', [new OnPipeMessageEvent(), 'handle']);
        $server->on('open', [new OnOpenEvent(), 'handle']);
        $server->on('message', [new OnMessageEvent(), 'handle']);
        $server->on('close', [new OnCloseEvent(), 'handle']);
        $server->on('request', [new OnRequestEvent($server), 'handle']);
        $server->on('task', [new OnTaskEvent(), 'handle']);
        $server->on('finish', [new OnFinishEvent(), 'handle']);
    }

}

