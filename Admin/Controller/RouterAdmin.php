<?php

namespace Swlib\Admin\Controller;

use Generate\Tables\Main\RouterTable;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\TextareaField;
use Swlib\Admin\Fields\TextArrayField;
use Swlib\Admin\Fields\TextField;
use Throwable;

class RouterAdmin extends AbstractAdmin
{


    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "页面配置";
        $config->tableName = RouterTable::class;
        $config->order = [
            RouterTable::LAST_TIME => 'desc'
        ];
    }



    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(field: RouterTable::ID, label: 'ID')->hideOnForm(),
            new TextField(field: RouterTable::NAME, label: '页面名称'),
            new TextField(field: RouterTable::URI, label: '路由地址')->setListMaxWidth(500),
            new Int2TimeField(field: RouterTable::LAST_TIME, label: '最后访问时间')->hideOnForm(),
            new NumberField(field: RouterTable::NUM, label: '访问次数'),
            new TextField(field: RouterTable::INFO, label: '页面简介'),
            new TextArrayField(field: RouterTable::KEYWORD, label: '页面关键字'),
            new TextareaField(field: RouterTable::DESC, label: '页面详细功能'),
        );
    }

}