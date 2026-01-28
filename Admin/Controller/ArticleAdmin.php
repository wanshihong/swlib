<?php

namespace Swlib\Admin\Controller;

use Generate\Tables\Main\ArticleTable;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\EditorField;
use Swlib\Admin\Fields\ImageField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\SwitchField;
use Swlib\Admin\Fields\TextField;
use Throwable;

class ArticleAdmin extends AbstractAdmin
{


    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "文章内容";
        $config->tableName = ArticleTable::class;
        $config->order = [
            ArticleTable::ID => 'desc'
        ];
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(field: ArticleTable::ID, label: 'ID')->hideOnForm(),
            new TextField(field: ArticleTable::TITLE, label: '标题'),
            new TextField(field: ArticleTable::SUB_TITLE, label: '副标题')->setRequired(false)->hideOnFilter(),
            new ImageField(field: ArticleTable::COVER, label: '封面')->setRequired(false)->hideOnFilter(),
            new EditorField(field: ArticleTable::CONTENT, label: '内容')->hideOnList()->hideOnFilter(),
            new SwitchField(field: ArticleTable::IS_ENABLE, label: '是否启用')->hideOnForm(),
            new TextField(field: ArticleTable::GROUP_POS, label: '分组位置')->setRequired(false),
        );
    }

}