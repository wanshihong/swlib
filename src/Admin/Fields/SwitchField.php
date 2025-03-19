<?php

namespace Swlib\Admin\Fields;

use Swlib\Admin\Utils\Func;

class SwitchField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/switch.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/switch.twig";

    // 启用状态的值
    public mixed $enableValue = 1;

    // 禁用状态的值
    public mixed $disabledValue = 0;

    public string $url = '';

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->url = Func::url('switch');

        $this->addJsFile('/admin/js/field-switch.js');

        // 设置默认值
        $this->setFormRequestAfter(function ($value) {
            if (empty($value)) {
                return $this->disabledValue;
            } else {
                return $this->enableValue;
            }
        });
    }


    /**
     * 设置启用状态的值
     * @param mixed $enableValue
     * @return $this
     */
    public function setEnableValue(mixed $enableValue): SwitchField
    {
        $this->enableValue = $enableValue;
        return $this;
    }

    /**
     * 设置禁用状态的值
     * @param mixed $disabledValue
     * @return $this
     */
    public function setDisabledValue(mixed $disabledValue): SwitchField
    {
        $this->disabledValue = $disabledValue;
        return $this;
    }
}