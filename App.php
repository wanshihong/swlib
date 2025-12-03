<?php
declare(strict_types=1);

namespace Swlib;

use Exception;
use Generate\ConfigEnum;
use ReflectionClass;
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
use Swlib\Parse\ast\AstCompiler;
use Swlib\Process\Process;
use Swlib\ServerEvents\ServerEventManager;
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
    private function parseInProcess(): void
    {
        if (ConfigEnum::APP_PROD === false) {
            // 开发过程过程中  删除解析锁，上线了可以删除；
            @unlink(RUNTIME_DIR . 'parse.lock');
        }

        //解析过了，不需要再次解析
        if (is_file(RUNTIME_DIR . 'parse.lock')) {
            return;
        }

        $startTime = microtime(true);

        // 删除模板缓存文件,模板文件在开发环境会自动生成，
        // 在生产环境会有缓存，所以每次生成文件之前删除
        echo "正在删除模板缓存文件..." . PHP_EOL;
        $stepStartTime = microtime(true);
        File::delDirectory(RUNTIME_DIR . 'twig');
        echo "删除模板缓存文件完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;


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

        // AST 编译生成 AOP/Transaction 代理类
        echo "正在编译 AOP/Transaction 代理类..." . PHP_EOL;
        $stepStartTime = microtime(true);
        new AstCompiler();
        echo "编译 AOP/Transaction 代理类完成，耗时: " . round(microtime(true) - $stepStartTime, 4) . "秒" . PHP_EOL;


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

        File::save(RUNTIME_DIR . 'parse.lock', 1);

        PoolMysql::close();
        PoolRedis::close();

        // 更新 composer 自动加载
        if (ConfigEnum::APP_PROD === true) {
            // 生产环境：去掉 dev 依赖，减小自动加载开销
            shell_exec('composer dump-autoload --no-dev --optimize');
        }
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

        // 添加自定义进程
        Process::run($server);


        $server->start();
    }


}

