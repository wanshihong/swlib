<?php

namespace Swlib\Admin\Controller;

use ReflectionException;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\CheckboxField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Manager\OptionManager;
use Swlib\Table\Db;
use Throwable;


class AdminManagerAdmin extends AbstractAdmin
{
    /**
     * @throws ReflectionException
     */
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "管理员";
        $reflection = Db::getTableReflection('AdminManagerTable');
        $config->tableName = $reflection->getName();
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $reflection = Db::getTableReflection('AdminManagerTable');
        $fields->setFields(
            new NumberField(field: $reflection->getConstant('ID'), label: 'ID')->hideOnForm(),
            new TextField(field: $reflection->getConstant('USERNAME'), label: '登录账号'),
            new CheckboxField(field: $reflection->getConstant('ROLES'), label: '角色')->setOptions(
                new OptionManager('ROLE_ADMIN', '登录权限'),
                new OptionManager('ROLE_SUPPER_ADMIN', '超级管理员'),
                new OptionManager('ROLE_OPERATION', '运营'),
                new OptionManager('ROLE_TEST', '测试'),
            ),
        );
    }

}