<?php

namespace Swlib\Admin\Fields;

use Swlib\Table\TableInterface;

class UrlField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/url.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/url.twig";


    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);

        // 设置查询条件
        $this->setFilterQuery(function (TableInterface $query, $value) {
            $query->addWhere($this->field, "%$value%", 'like');
        });
    }

}