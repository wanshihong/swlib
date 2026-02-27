<?php

namespace Swlib\Admin\Fields;


use Swlib\Table\Interface\TableInterface;
use Swlib\Utils\Url;

class TextField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/text.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/text.twig";

    // 列表行内编辑开关
    public bool $listInlineEdit = false;

    // 列表行内编辑提交地址（默认 edit）
    public string $listInlineEditUrl = '';

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->listInlineEditUrl = Url::generateUrl('edit');

        // 设置查询条件
        $this->setFilterQuery(function (TableInterface $query, $value) {
            $query->addWhere($this->field, "%$value%", 'like');
        });
    }

    /**
     * 开启/关闭列表行内编辑
     */
    public function setListInlineEdit(bool $enable = true): static
    {
        $this->listInlineEdit = $enable;
        if ($enable) {
            $this->setTemplateList('fields/lists/text-inline-edit.twig');
            $this->addJsFile('/admin/js/field-text-inline.js');
        } else {
            $this->setTemplateList('fields/lists/text.twig');
        }
        return $this;
    }

    /**
     * 设置行内编辑接口地址
     */
    public function setListInlineEditUrl(string $url): static
    {
        $this->listInlineEditUrl = $url;
        return $this;
    }

}
