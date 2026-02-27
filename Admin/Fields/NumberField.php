<?php

namespace Swlib\Admin\Fields;

use InvalidArgumentException;

class NumberField extends TextField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/number.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/number.twig";

    private ?int $min = null;
    private ?int $max = null;

    public function __construct(string $field, string $label, string $dbName = 'default')
    {
        parent::__construct($field, $label, $dbName);
        $this->addJsFile('/admin/js/field-number.js');
    }

    public function setMin(int $min): static
    {
        if ($this->max !== null && $min > $this->max) {
            throw new InvalidArgumentException('Minimum value cannot be greater than the current maximum value.');
        }
        $this->min = $min;
        $this->addAttribute('data-validate-min', (string)$min);
        $this->addAttribute('data-field-label', $this->label);
        return $this;
    }

    public function setMax(int $max): static
    {
        if ($this->min !== null && $max < $this->min) {
            throw new InvalidArgumentException('Maximum value cannot be less than the current minimum value.');
        }
        $this->max = $max;
        $this->addAttribute('data-validate-max', (string)$max);
        $this->addAttribute('data-field-label', $this->label);
        return $this;
    }

}
