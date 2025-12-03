<?php

namespace Swlib\Admin\Fields;


use Swlib\Admin\Trait\Field\FieldArrayTrait;

class TextArrayField extends TextField
{

    use FieldArrayTrait;

    // 列表页面自定义模板
    public string $templateForm = "fields/form/text-array.twig";


    /**
     * 表单页面 可供选择的数据
     * @var array
     */
    public array $options = [];

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);

        $this->addJsFile('/admin/js/text-array.js');


        $this->arrayFieldInit();

    }


}