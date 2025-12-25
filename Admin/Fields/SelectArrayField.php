<?php

namespace Swlib\Admin\Fields;


use Swlib\Admin\Manager\OptionManager;
use Swlib\Table\Interface\TableDtoInterface;
use Swlib\Table\Interface\TableInterface;
use Swlib\Utils\DataConverter;
use Throwable;

class SelectArrayField extends SelectField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/select-array.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/select-array.twig";

    public array $showTexts = [];

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);

        $this->addJsFile('/admin/js/field-select-array.js');

        $this->setFormCreate(function (SelectArrayField $field) {
            $field->showTexts[] = new OptionManager('', '');
        });

        // 设置查询条件
        $this->setFilterQuery(function (TableInterface $query, $value) {
            $query->addWhere($this->field, "%$value%", 'like');
        });

        // 表单页面接收值后，数据格式化
        $this->setFormRequestAfter(function ($value) {
            if (empty($value)) {
                return '';
            }

            $save = [];
            foreach (array_unique($value) as $v) {
                if (is_numeric($v)) {
                    $save[] = intval($v);
                } else {
                    $save[] = $v;
                }
            }
            // 逗号分割存储
            return implode(',', $save);
        });
    }

    /**
     * 表单编辑的时候，设置字段的值
     * 请勿手动调用，由框架自动调用
     * @param TableDtoInterface $dto
     * @return $this
     * @throws Throwable
     */
    public function frameworkEditFill(TableDtoInterface $dto): static
    {
        // 读取字段的值
        $value = $dto->getByField($this->field);

        if (empty($value)) {
            $this->value = [];
            $this->showTexts[] = new OptionManager('', '');
            return $this;
        }


        // 尝试理解逗号分割存储的
        try {
            $tempValue = explode(',', $value);
        } catch (Throwable) {
            $tempValue = [];
        }
        if (empty($tempValue)) {
            // 尝试理解 json 数组存储的
            try {
                $tempValue = json_decode($value, true);
            } catch (Throwable) {
                $tempValue = [];
            }
        }

        $this->value = $tempValue;


        // 自定义了 格式化
        if ($this->formFormat) {
            list($value, $optionManagers) = call_user_func($this->formFormat, $value, $dto);
            $this->showTexts = $optionManagers;
            $this->value = $value;
            return $this;
        }

        if ($this->value) {
            foreach ($this->value as $v) {
                if ($this->textField) {
                    // 显示的文本
                    $this->showTexts[] = new OptionManager($v, $this->_getShowText($v));
                } elseif ($this->options) {
                    //关联选择
                    foreach ($this->options as $option) {
                        if ($option->id == $v) {
                            $this->showTexts[] = new OptionManager($v, $option->text ?: "ID:$v");
                        }
                    }
                }
            }
        }

        return $this;
    }


    /**
     * 列表 设置字段的值
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @param mixed $value
     * @param TableInterface $table
     * @return $this
     * @throws Throwable
     */
    public function frameworkSetValue(mixed $value, TableInterface $table): static
    {
        if ($this->listFormat && is_callable($this->listFormat)) {
            $value = call_user_func($this->listFormat, $value, $table);
        }
        $value = $value == null && $this->default !== null ? $this->default : $value;
        if (empty($value)) {
            $this->value = [];
            $this->showTexts = [];
            return $this;
        }

        $valueArr = DataConverter::convertToArray($value);
        foreach ($valueArr as $v) {
            if ($this->options) {
                foreach ($this->options as $option) {
                    if ($option->id == $v) {
                        $this->showTexts[] = new OptionManager($v, $option->text ?: "ID:$v");
                    }
                }
            } else if ($this->table && $v) {
                $text = $this->_getShowText($v);
                $this->showTexts[] = new OptionManager($v, $text ?: '');
            }
        }

        return $this;
    }


}