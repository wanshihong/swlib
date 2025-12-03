<?php

namespace Swlib\Admin\Fields;

class NumberField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/number.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/number.twig";

}