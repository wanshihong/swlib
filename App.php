<?php
declare(strict_types=1);

namespace Swlib;

use Exception;
use Generate\ConfigEnum;
use ReflectionClass;
use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Parse\ast\AstCompiler;
use Swlib\Parse\CopyProtoFile;
use Swlib\Parse\ParseAdminConfig;
use Swlib\Parse\ParseConfig;
use Swlib\Parse\ConfigValidator;
use Swlib\Parse\ParseDevTool;
use Swlib\Parse\ParseEvent;
use Swlib\Parse\ParseLanguage;
use Swlib\Parse\ParseCrontab;
use Swlib\Parse\ParseProcess;
use Swlib\Parse\ParseRouter;
use Swlib\Parse\ParseTable;
use Swlib\Parse\TableBack;
use Swlib\Crontab\CrontabScheduler;
use Swlib\Process\Process;
use Swlib\ServerEvents\ServerEventManager;
use Swlib\Utils\ConsoleColor;
use Swlib\Utils\File;
use Swoole\WebSocket\Server;
use Throwable;
use function Swoole\Coroutine\run;

define('SWLIB_DIR', __DIR__ . DIRECTORY_SEPARATOR);

class App
{

    public function __construct()
    {
        // 解析配置文件
        echo "正在解析配置文件..." . PHP_EOL;
        $stepStartTime = microtime(true);

        run(fn: function () {
            new ParseConfig();
        });

        echo "解析配置文件完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;

        // 验证配置
        echo "正在验证配置..." . PHP_EOL;
        $stepStartTime = microtime(true);

        run(fn: function () {
            ConfigValidator::validate();
        });

        echo "配置验证完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;


        // 创建运行目录
        array_map(function ($path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }, [PUBLIC_DIR, RUNTIME_DIR, RUNTIME_DIR . 'log/']);

        if (ConfigEnum::APP_PROD === false) {
            File::delDirectory(RUNTIME_DIR . 'twig');
        }

    }


    /**
     * @throws Exception
     */
    public function generateDevSSL(array $config): array
    {
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
     * 在独立进程中执行解析操作
     * @throws Throwable
     */
    public function parseInProcess(): void
    {
        $startTime = microtime(true);

        // 定义解析步骤
        $parseSteps = [
            ['name' => '删除模板缓存文件', 'action' => static fn() => File::delDirectory(RUNTIME_DIR . 'twig')],
            ['name' => '删除编译文件', 'action' => static fn() => File::delDirectory(RUNTIME_DIR . 'Proxy')],
            ['name' => '解析后台配置', 'action' => static fn() => new ParseAdminConfig()],
            ['name' => '解析表格字段', 'action' => static fn() => new ParseTable()],
            ['name' => '解析开发工具路由', 'action' => static fn() => new ParseDevTool()],
            ['name' => '解析项目路由', 'action' => static fn() => new ParseRouter()],
            ['name' => '复制 Proto 文件', 'action' => static fn() => new CopyProtoFile()],
            ['name' => '解析语言文件', 'action' => static fn() => new ParseLanguage()],
            ['name' => '解析自定义进程', 'action' => static fn() => new ParseProcess()],
            ['name' => '解析定时任务', 'action' => static fn() => new ParseCrontab()],
            ['name' => '解析事件管理器', 'action' => static fn() => new ParseEvent()],
            ['name' => '编译 AOP/Transaction 代理类', 'action' => static fn() => new AstCompiler()],
            ['name' => '备份数据库', 'action' => static fn() => new TableBack()],
            ['name' => '移动静态文件', 'action' => static fn() => File::copyDirectory(SWLIB_DIR . '/Admin/static', PUBLIC_DIR . '/admin')],
        ];

        // 执行解析步骤
        foreach ($parseSteps as $step) {
            $stepStartTime = microtime(true);
            try {
                ConsoleColor::writeStep($step['name']);
                call_user_func($step['action']);

                $duration = microtime(true) - $stepStartTime;
                ConsoleColor::writeStep($step['name'], 'success', $duration);
            } catch (Throwable $e) {
                $duration = microtime(true) - $stepStartTime;
                ConsoleColor::writeStep($step['name'], 'error', $duration);
                ConsoleColor::writeErrorToStderr("{$step['name']}失败: " . $e->getMessage(), $e);
                throw $e; // 重新抛出异常，确保进程会退出
            }
        }

        $totalDuration = microtime(true) - $startTime;
        ConsoleColor::writeSuccess("所有解析操作完成，总耗时: {$totalDuration}秒");


        PoolMysql::close();
        PoolRedis::close();
    }

    /**
     * 初始化服务器，绑定事件和添加进程
     * @throws Throwable
     */
    public function startSwooleServer(array $config): void
    {
        // 在独立的 Swoole 进程中执行解析操作，避免在当前进程中提前加载 App 类
        $parseProcess = new \Swoole\Process(function () {
            try {
                run(fn: function () {
                    $this->parseInProcess();
                });
            } catch (Throwable $e) {
                fwrite(STDERR, "[parse] " . $e->getMessage() . PHP_EOL);
                fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
                exit(1);
            }
        });

        // 启动解析进程
        $parseProcess->start();

        // 等待解析进程完成
        $parseResult = \Swoole\Process::wait();
        if ($parseResult['code'] !== 0) {
            echo "解析进程执行失败，退出码：{$parseResult['code']}" . PHP_EOL;
            exit($parseResult['code']);
        }

        // 创建服务器实例
        $reflection = new ReflectionClass(ConfigEnum::class);
        $useHttps = $reflection->hasConstant('HTTPS') && $reflection->getConstant('HTTPS');

        if ($useHttps) {
            // https 启动
            $config = $this->generateDevSSL($config);
            $server = new Server("0.0.0.0", ConfigEnum::PORT, SWOOLE_PROCESS, ConfigEnum::APP_PROD === false ? SWOOLE_SOCK_TCP | SWOOLE_SSL : 0);
        } else {
            // http 启动
            $server = new Server("0.0.0.0", ConfigEnum::PORT, SWOOLE_PROCESS);
        }

        $server->set($config);

        // 使用事件管理器统一绑定所有服务器事件
        ServerEventManager::bindEvents($server);

        // 添加自定义进程（Process 功能）
        Process::run($server);

        // 添加 Crontab 调度器进程（独立的 Crontab 功能）
        CrontabScheduler::run($server);


        $server->start();
    }


}

