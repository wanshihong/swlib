<?php

namespace Swlib\Admin\Fields;


class ColorField extends AbstractField
{

    public string $templateForm = "fields/form/color.twig";


    public string $templateList = "fields/lists/color.twig";

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->hideOnFilter();
    }

}