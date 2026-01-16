<?php

namespace Swlib\Admin\Controller;

use Generate\Tables\Main\RouterHisTable;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\TextField;
use Throwable;

class RouterHisAdmin extends AbstractAdmin
{
    
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "页面访问历史";
        $config->tableName = RouterHisTable::class;
        $config->order = [
            RouterHisTable::TIME => 'desc'
        ];
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(field: RouterHisTable::ID, label: 'ID')->hideOnForm(),
            new TextField(field: RouterHisTable::URI, label: '路由')->setListMaxWidth(500),
            new TextField(field: RouterHisTable::IP, label: 'IP'),
            new Int2TimeField(field: RouterHisTable::TIME, label: '时间')->hideOnForm(),
        );
    }

}