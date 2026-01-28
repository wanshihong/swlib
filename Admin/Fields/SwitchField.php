<?php

namespace Swlib\Admin\Fields;

use Swlib\Utils\Url;

class SwitchField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/switch.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/switch.twig";

    public string $templateFilter = "fields/filter/switch.twig";


    // 启用状态的值
    public mixed $enableValue = 1;

    // 禁用状态的值
    public mixed $disabledValue = 0;


    // 启用状态的文本
    public string $enableText = 'ON';

    // 禁用状态的文本
    public string $disabledText = 'OFF';


    /**
     * 列表页面点击开关以后,调用那个接口同步开关的值
     * @var string
     */
    public string $url = '';

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->url = Url::generateUrl('switch');

        $this->addJsFile('/admin/js/field-switch.js');

        // 设置默认值
        $this->setFormRequestAfter(function ($value) {
            if (empty($value)) {
                return $this->disabledValue;
            } else {
                return $this->enableValue;
            }
        });
        $this->hideOnForm();
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

    public function setEnableText(string $enableText): SwitchField
    {
        $this->enableText = $enableText;
        return $this;
    }

    public function setDisabledText(string $disabledText): SwitchField
    {
        $this->disabledText = $disabledText;
        return $this;
    }
}