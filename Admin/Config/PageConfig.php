<?php

namespace Swlib\Admin\Config;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Admin\Trait\StaticTrait;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Exception\RedirectException;
use Swlib\Utils\Language;
use Throwable;

class PageConfig implements PermissionInterface
{
    use PermissionTrait;
    use StaticTrait;

    public string $pageName = ''; // 页面标题


    // 数据库查询表格，
    //  Generate\Tables\* 命名空间下的文件
    // 例如 ZpTagTable::class
    public string $tableName = '';
    public bool $autoTranslate = true;

    // 查询排序
    public array $order = [
        // field1=>asc
        // field2=>desc
    ];


    public int $querySize = 12;

    // 第一页和最后一页显示完了以后
    // 在列表页面上分页 当前页码 前后各增加 配置的条数
    // 1...  6 7 8 9 10 ... 100
    public int $pageShowSize = 5;


    /**
     * 判断是否有查看权限,无权限重定向页面
     *
     * 框架会自动调用这个方法，请不要手动调用
     * 框架会自动调用这个方法，请不要手动调用
     * 框架会自动调用这个方法，请不要手动调用
     * @return void
     * @throws Throwable
     */
    public function frameworkCheckFieldsPermissions(): void
    {
        if (AdminUserManager::checkPermissionsByConfig($this) === false) {
            throw new AppException(AppErr::ADMIN_PERMISSION_DENIED, AdminManager::getInstance()->noAccessUrl);
        }
    }

    /**
     * 模板引擎中获取页面标题
     * @throws Throwable
     */
    public function getPageName(): string
    {
        if ($this->autoTranslate) {
            return Language::get($this->pageName);
        }
        return $this->pageName;
    }

    public function setPageName(string $pageName, $autoTranslate = true): self
    {
        $this->autoTranslate = $autoTranslate;
        $this->pageName = $pageName;
        return $this;
    }


}