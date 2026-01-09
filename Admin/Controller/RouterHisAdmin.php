<?php

namespace Swlib\Admin\Controller;

use ReflectionException;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\TextField;
use Swlib\Table\Db;
use Throwable;

class RouterHisAdmin extends AbstractAdmin
{

    /**
     * @throws ReflectionException
     */
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "页面访问历史";
        $reflection = Db::getTableReflection('RouterHisTable');
        $config->tableName = $reflection->getName();
        $config->order = [
            $reflection->getConstant('TIME') => 'desc'
        ];
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $reflection = Db::getTableReflection('RouterHisTable');
        $fields->setFields(
            new NumberField(field: $reflection->getConstant('ID'), label: 'ID')->hideOnForm(),
            new TextField(field: $reflection->getConstant('URI'), label: '路由')->setListMaxWidth(500),
            new TextField(field: $reflection->getConstant('IP'), label: 'IP'),
            new Int2TimeField(field: $reflection->getConstant('TIME'), label: '时间')->hideOnForm(),
        );
    }

}