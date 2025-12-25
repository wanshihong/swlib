<?php

namespace Swlib\Admin\Trait;

trait AttrTrait
{

    public array $attributes = [];
    public array $classes = [];

    public string $elemId = '';


    // 字段是否必填，用于表单页面
    public bool $required = true;

    // 是否只读
    public bool $readonly = false;

    /**
     * 设置字段在表单页面是否必填
     * @param bool $required
     * @return $this
     */
    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }


    /**
     * 设置字段在表单页面是否只读
     */
    public function setReadonly(bool $readonly): static
    {
        $this->readonly = $readonly;
        return $this;
    }


    public function addAttribute(string $attr, string|int|bool $value): static
    {
        $this->attributes[$attr] = $value;
        return $this;
    }

    public function addClass(string $className): static
    {
        if (!in_array($className, $this->classes)) {
            $this->classes[] = $className;
        }
        return $this;
    }

    /**
     * 设置元素 ID， 默认是用字段名称作为元素ID
     * @param string $elemIdName
     * @return static
     */
    public function setId(string $elemIdName): static
    {
        $this->elemId = $elemIdName;
        return $this;
    }

}