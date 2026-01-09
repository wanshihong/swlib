<?php

namespace Swlib\Parse\Admin;

use Generate\ConfigEnum;
use Swlib\Utils\File;

/**
 * 后台脚手架解析器
 * 根据 ADMIN_CONFIG_PATH 配置自动生成后台目录和基础控制器
 */
class ParseAdminScaffold
{

    /**
     * 执行解析和生成
     * @return void
     */
    public function __construct()
    {
        // 检查是否配置了后台命名空间
        $adminNamespace = ConfigEnum::get('ADMIN_NAMESPACE');

        if (empty($adminNamespace)) {
            return;
        }

        echo "检测到后台配置: $adminNamespace\n";

        // 解析命名空间，获取目录路径
        $parts = explode('\\', $adminNamespace);
        $namespace = implode('\\', $parts);
        $directory = ROOT_DIR .  implode(DIRECTORY_SEPARATOR, $parts);

        echo "后台目录: $directory\n";

        // 如果目录不存在，创建目录
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
            echo "创建后台目录: $directory\n";
        }

        // 生成基础控制器文件
        self::generateAdminConfigController($namespace, $directory);
        self::generateController($namespace, $directory,'AdminManagerAdmin');
        self::generateController($namespace, $directory,'ArticleAdmin');
        self::generateController($namespace, $directory,'ConfigAdmin');
        self::generateController($namespace, $directory,'Dashboard');
        self::generateController($namespace, $directory,'LanguageAdmin');
        self::generateController($namespace, $directory,'LoginAdmin');
        self::generateController($namespace, $directory,'RouterAdmin');
        self::generateController($namespace, $directory,'RouterHisAdmin');


        echo "后台脚手架生成完成\n";
    }


    /**
     * 生成 AdminConfig 控制器
     * @param string $namespace
     * @param string $directory
     * @return void
     */
    private static function generateAdminConfigController(string $namespace, string $directory): void
    {
        $filePath = $directory . DIRECTORY_SEPARATOR . 'AdminConfig.php';

        if (file_exists($filePath)) {
            echo "AdminConfig.php 已存在，跳过生成\n";
            return;
        }

        // 从命名空间中提取后台标识（例如 AdminXsom756）
        $parts = explode('\\', $namespace);
        $adminIdentifier = end($parts);

        $content = <<<PHP
<?php

namespace $namespace;

use Generate\RouterPath;
use Swlib\Admin\Config\AdminConfigAbstract;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Menu\Menu;
use Swlib\Admin\Menu\MenuGroup;
use Swlib\Utils\Url;
use Throwable;

class AdminConfig extends AdminConfigAbstract
{
    public function Init(AdminManager \$layout): void
    {
        // 首页地址
        \$layout->adminIndexUrl = RouterPath::{$adminIdentifier}SystemDashboardIndex;

        // 退出登录路由
        \$layout->logoutUrl = RouterPath::{$adminIdentifier}SystemLoginAdminLogout;
        \$layout->loginUrl = RouterPath::{$adminIdentifier}SystemLoginAdminLogin;

        // 修改密码路由
        \$layout->changePasswordUrl = RouterPath::{$adminIdentifier}SystemLoginAdminChangePassword;
        \$layout->noAccessUrl = RouterPath::{$adminIdentifier}SystemDashboardNoAccess;

//        \$layout->uploadUrl = Url::generateUrl(RouterPath::ApiFileUpload);
    }

    /**
     * @throws Throwable
     */
    public function configAdminTitle(): string
    {
        return '管理后台';
    }

    /**
     * @throws Throwable
     */
    public function configMenus(): array
    {
        return [
            // 在这里配置菜单
             new MenuGroup(label: '系统', icon: 'bi bi-chevron-double-right')->setMenus(
                new  Menu(label: '管理员', url: RouterPath::{$adminIdentifier}SystemAdminManagerAdminLists),
                new Menu(label: '翻译配置', url: RouterPath::{$adminIdentifier}SystemLanguageAdminLists),
                new Menu(label: '页面配置', url: RouterPath::{$adminIdentifier}SystemRouterAdminLists),
                new Menu(label: '访问历史', url: RouterPath::{$adminIdentifier}SystemRouterHisAdminLists),
                new Menu(label: '系统配置', url: RouterPath::{$adminIdentifier}SystemConfigAdminLists),
                new Menu(label: '文章内容', url: RouterPath::{$adminIdentifier}SystemArticleAdminLists),
            ),
        ];
    }
}

PHP;

        File::save($filePath, $content);
        echo "生成文件: $filePath\n";
    }


    /**
     * 生成 Language 控制器
     * @param string $namespace
     * @param string $directory
     * @param $className
     * @return void
     */
    private static function generateController(string $namespace, string $directory,$className): void
    {
        $filePath = $directory . "/Controller/System/$className.php";

        if (file_exists($filePath)) {
            echo "$className.php 已存在，跳过生成\n";
            return;
        }

        $content = <<<PHP
<?php

namespace $namespace\Controller\System;

class $className extends \Swlib\Admin\Controller\\{$className}
{

}

PHP;

        File::save($filePath, $content);
        echo "生成文件: $filePath\n";
    }

}



