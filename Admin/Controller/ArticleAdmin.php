<?php

namespace Swlib\Admin\Controller;

use ReflectionException;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\EditorField;
use Swlib\Admin\Fields\ImageField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\SwitchField;
use Swlib\Admin\Fields\TextField;
use Swlib\Table\Db;
use Throwable;

class ArticleAdmin extends AbstractAdmin
{

    /**
     * @throws ReflectionException
     */
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "文章内容";
        $reflection = Db::getTableReflection('ArticleTable');
        $config->tableName = $reflection->getName();
        $config->order = [
            $reflection->getConstant('ID') => 'desc'
        ];
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $reflection = Db::getTableReflection('ArticleTable');
        $fields->setFields(
            new NumberField(field: $reflection->getConstant('ID'), label: 'ID')->hideOnForm(),
            new TextField(field: $reflection->getConstant('TITLE'), label: '标题'),
            new TextField(field: $reflection->getConstant('SUB_TITLE'), label: '副标题')->setRequired(false)->hideOnFilter(),
            new ImageField(field: $reflection->getConstant('COVER'), label: '封面')->setRequired(false)->hideOnFilter(),
            new EditorField(field: $reflection->getConstant('CONTENT'), label: '内容')->hideOnList()->hideOnFilter(),
            new SwitchField(field: $reflection->getConstant('IS_ENABLE'), label: '是否启用')->hideOnForm(),
            new TextField(field: $reflection->getConstant('GROUP_POS'), label: '分组位置')->setRequired(false),
        );
    }

}