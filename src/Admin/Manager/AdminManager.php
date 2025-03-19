<?php

namespace Swlib\Admin\Manager;

use Generate\AdminConfigMap;
use Generate\TableFieldMap;
use Swlib\Admin\Menu\MenuGroup;
use Swlib\Admin\Utils\Func;
use Swlib\Enum\CtxEnum;
use Swlib\Table\Db;
use Swlib\Utils\Language;
use Swlib\Utils\Twig;
use Throwable;
use Twig\TwigFunction;

class AdminManager
{
    public string $title = '后台管理';
    public string $uploadUrl = '';
    public string $adminIndexUrl = '';

    // 退出登录路由，后台模板中有调用，再页面最右上角
    public string $logoutUrl = '';
    public string $loginUrl = '';

    // 退出登录路由，后台模板中有调用，再页面最右上角
    public string $changePasswordUrl = '';

    // 无权限路由
    public string $noAccessUrl = '';

    // 设置语言路由
    public string $setLanguageUrl = '';
    // 所有语言列表
    public array $languages = [];


    private static ?AdminManager $instance = null;


    // 私有化构造函数

    /**
     * @throws Throwable
     */
    private function __construct()
    {
        $this->languages = Language::getLanguages();

        $config = AdminConfigMap::Init;
        $className = $config[0];
        $methodName = $config[1];
        (new $className)->$methodName($this);

        $twig = Twig::getInstance();

        $twig->addFunc('adminLayout', [__CLASS__, 'getInstance']);
        $twig->addFunc('adminUser', [AdminUserManager::class, 'getUser']);

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


    /**
     * 获取当前的菜单
     * @return MenuGroup[]
     */
    public function getMenus(): array
    {
        $config = AdminConfigMap::ConfigMenus;
        $className = $config[0];
        $methodName = $config[1];
        $menus = (new $className)->$methodName();
        // 遍历菜单，设置选中状态
        /** @var MenuGroup $menuGroup */
        foreach ($menus as $menuGroup) {
            $menuGroup->checkActive();
        }
        return $menus;
    }

    /**
     * @throws Throwable
     */
    public function getTitle(): string
    {
        $config = AdminConfigMap::ConfigTitle;
        $className = $config[0];
        $methodName = $config[1];
        $adminTitle = (new $className)->$methodName();
        return Language::get($adminTitle);
    }


    /**
     * 获取当前的语言
     * @return string
     */
    public function getLang(): string
    {
        return $this->languages[CtxEnum::Lang->get()] ?? '';
    }

}