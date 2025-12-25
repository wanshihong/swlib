<?php

namespace Swlib\Admin\Fields;


use Swlib\Admin\Manager\OptionManager;
use Swlib\Table\Interface\TableInterface;
use Throwable;

class CheckboxField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/text.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/checkbox.twig";

    // 过滤器页面自定义模板
    public string $templateFilter = "fields/filter/checkbox.twig";


    /**
     * 表单页面 可供选择的数据
     * @var OptionManager[]
     */
    public array $options = [];

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);


        // 设置查询条件
        $this->setFilterQuery(function (TableInterface $query, $value) {
            $query->addWhere($this->field, "%$value%", 'like');
        });

        // 表单页面接收值后，数据格式化
        $this->setFormRequestAfter(function ($value) {
            if (empty($value)) {
                return '[]';
            }
            return json_encode($value,JSON_UNESCAPED_UNICODE);
        });


        // 表单编辑 回填数据到表单的时候数据格式化
        $this->setFormFormat(function ($value) {
            $value = json_decode($value, true);
            foreach ($this->options as $option) {
                $option->checked = in_array($option->id, $value);
            }
            return $value;
        });


        // 列表页面数据格式化
        $this->setListFormat(function (string $value) {
            if (empty($value)) return '';
            try {
                $arr = array_filter(json_decode($value, true));
                if (empty($arr)) return '';
                $ret = [];
                foreach ($arr as $v) {
                    foreach ($this->options as $option) {
                        if ($v == $option->id) {
                            $ret[] = $option->text;
                        }
                    }
                }

                return implode(',', $ret);
            } catch (Throwable) {
                return $value;
            }
        });
    }


    /**
     * 可供选择的数据
     * @param OptionManager[] $options
     * @return $this
     */
    public function setOptions(...$options): static
    {
        $this->options = $options;
        return $this;
    }

}