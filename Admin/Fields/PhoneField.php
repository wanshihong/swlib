<?php

namespace Swlib\Admin\Fields;

class PhoneField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/number.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/phone.twig";

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->addJsFile('/admin/js/field-phone.js');
    }
}