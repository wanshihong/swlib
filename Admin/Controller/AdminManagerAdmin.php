<?php

namespace Swlib\Admin\Controller;

use Generate\Tables\CommonApi\AdminManagerTable;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\CheckboxField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Manager\OptionManager;
use Throwable;


class AdminManagerAdmin extends AbstractAdmin
{
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "管理员";
        $config->tableName = AdminManagerTable::class;
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(field: AdminManagerTable::ID, label: 'ID')->hideOnForm(),
            new TextField(field: AdminManagerTable::USERNAME, label: '登录账号'),
            new CheckboxField(field: AdminManagerTable::ROLES, label: '角色')->setOptions(
                new OptionManager('ROLE_ADMIN', '登录权限'),
                new OptionManager('ROLE_SUPPER_ADMIN', '超级管理员'),
                new OptionManager('ROLE_OPERATION', '运营'),
                new OptionManager('ROLE_TEST', '测试'),
            ),
        );
    }

}