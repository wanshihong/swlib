<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use RuntimeException;
use Swlib\Exception\AppErr;
use Swlib\Admin\Controller\Helper\ControllerHelper;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\Table\Db;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Twig
{


    private static ?Twig $instance = null;

    public Environment $twig;

    public array $templatesDir = [];

    // 私有化构造函数
    private function __construct()
    {
        $runtimeDir = RUNTIME_DIR . 'twig/';
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0777, true);
        }

        $this->addDir(SWLIB_DIR . '/Admin/Templates/');
        $this->addDir(ROOT_DIR . 'templates');


        if (ConfigEnum::APP_PROD === false) {
            // 不是生产环境 增加 DevTool 目录
            $this->addDir(SWLIB_DIR . 'DevTool/Templates/');
        }

        if (empty($this->templatesDir)) {
            // 未配置模板目录
            throw new RuntimeException(AppErr::TEMPLATE_DIR_NOT_CONFIGURED);
        }

        $loader = new FilesystemLoader($this->templatesDir);

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
                'url' => new TwigFunction('url', [Url::class, 'generateUrl']),
                'lang' => new TwigFunction('lang', [Language::class, 'get']),
                'getFieldAsByName' => new TwigFunction('getFieldAsByName', [Db::class, 'getFieldAsByName']),
                'getCurrentAction' => new TwigFunction('getCurrentAction', [ControllerHelper::class, 'getCurrentAction']),
                'adminLayout' => new TwigFunction('adminLayout', [AdminManager::class, 'getInstance']),
                'adminUser' => new TwigFunction('adminUser', [AdminUserManager::class, 'getUser']),
                default => null,
            };
        });
    }


    private function addDir($dir): void
    {
        if (is_dir($dir)) {
            $this->templatesDir[] = $dir;
        }
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
