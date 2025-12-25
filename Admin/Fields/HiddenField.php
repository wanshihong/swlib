<?php

namespace Swlib\Admin\Fields;


class HiddenField extends AbstractField
{

    public string $templateForm = "fields/form/hidden.twig";


    public string $templateFilter = "fields/filter/hidden.twig";

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->hideOnList()->hideOnDetail();
    }

}