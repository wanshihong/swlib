<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\Admin\Utils\Func;
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

        $this->twig->registerUndefinedFunctionCallback(function ($name) {
            return match ($name) {
                'url' => new TwigFunction('url', [Func::class, 'url']),
                'lang' => new TwigFunction('lang', [Language::class, 'get']),
                'getFieldAsByName' => new TwigFunction('getFieldAsByName', [Db::class, 'getFieldAsByName']),
                'adminLayout' => new TwigFunction('adminLayout', [AdminManager::class, 'getInstance']),
                'adminUser' => new TwigFunction('adminUser', [AdminUserManager::class, 'getUser']),
                default => null,
            };
        });
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
}