<?php

namespace Swlib\Admin\Controller;

use ReflectionException;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\TextareaField;
use Swlib\Admin\Fields\TextArrayField;
use Swlib\Admin\Fields\TextField;
use Swlib\Table\Db;
use Throwable;

class RouterAdmin extends AbstractAdmin
{

    /**
     * @throws ReflectionException
     */
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "页面配置";
        $reflection = Db::getTableReflection('RouterTable');
        $config->tableName = $reflection->getName();
        $config->order = [
            $reflection->getConstant('LAST_TIME') => 'desc'
        ];
    }



    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $reflection = Db::getTableReflection('RouterTable');
        $fields->setFields(
            new NumberField(field: $reflection->getConstant('ID'), label: 'ID')->hideOnForm(),
            new TextField(field: $reflection->getConstant('NAME'), label: '页面名称'),
            new TextField(field: $reflection->getConstant('URI'), label: '路由地址')->setListMaxWidth(500),
            new Int2TimeField(field: $reflection->getConstant('LAST_TIME'), label: '最后访问时间')->hideOnForm(),
            new NumberField(field: $reflection->getConstant('NUM'), label: '访问次数'),
            new TextField(field: $reflection->getConstant('INFO'), label: '页面简介'),
            new TextArrayField(field: $reflection->getConstant('KEYWORD'), label: '页面关键字'),
            new TextareaField(field: $reflection->getConstant('DESC'), label: '页面详细功能'),
        );
    }

}