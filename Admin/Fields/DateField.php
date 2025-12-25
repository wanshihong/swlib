<?php

namespace Swlib\Admin\Fields;


use Swlib\Table\Interface\TableInterface;

class DateField extends TextField
{

    // 列表页面自定义模板
    public string $templateFilter = "fields/filter/date.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/date.twig";


    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);

        // 设置查询条件
        $this->setFilterQuery(function (TableInterface $query, $value) {
            $query->addWhere($this->field, "%$value%", 'like');
        });
    }

}