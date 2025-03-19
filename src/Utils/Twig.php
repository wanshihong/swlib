<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use Swlib\Admin\Utils\Func;
use Swlib\DataManager\WorkerManager;
use Swlib\Table\Db;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Twig
{


    private static ?Twig $instance = null;

    public Environment $twig;

    // 私有化构造函数
    private function __construct()
    {
        $runtimeDir = RUNTIME_DIR . 'twig/';
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0777, true);
        }

        $loader = new FilesystemLoader([
            SWLIB_DIR . '/Admin/Templates/',
            ROOT_DIR . 'templates'
        ]);
        
        $this->twig = new Environment($loader, [
            'cache' => $runtimeDir,
            // 不是生产环境 才开启 debug
            'debug' => ConfigEnum::APP_PROD === false
        ]);

        if (ConfigEnum::APP_PROD === false) {
            // 不是生产环境 才开启 debug
            $this->twig->addExtension(new DebugExtension());
        }

        // 添加 url 函数 ,在可以在模板中调用
        $this->addFunc('url', [Func::class, 'url']);
        // 添加翻译函数
        $this->addFunc('lang', [Language::class, 'get']);
        // 添加 根据字段名称获取数据库字段别名 函数
        $this->addFunc('getFieldAsByName', [Db::class, 'getFieldAsByName']);

    }


    private function __clone()
    {

    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addFunc(string $funcName, array $runnable): void
    {
        $key = "addTwigFuncName:$funcName";
        if (WorkerManager::get($key)) {
            return;
        }
        $this->twig->addFunction(new TwigFunction($funcName, $runnable));
        WorkerManager::set($key, 1);
    }

}