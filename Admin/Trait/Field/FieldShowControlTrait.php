<?php

namespace Swlib\Admin\Trait\Field;

trait FieldShowControlTrait
{

    // 在表单页面是否显示
    public bool $fieldShowForm = true;

    // 在列表页面是否显示
    public bool $fieldShowList = true;
    public bool $fieldShowDetail = true;

    // 字段是否显示到过滤器
    public bool $fieldShowFilter = true;


    /**
     * 控制整体是否显示
     * @param bool $condition
     * @return FieldShowControlTrait
     */
    public function showCondition(bool $condition): static
    {
        $this->fieldShowForm = $condition;
        $this->fieldShowList = $condition;
        $this->fieldShowDetail = $condition;
        $this->fieldShowFilter = $condition;
        return $this;
    }

    public function onlyOnList(): static
    {
        $this->fieldShowList = true;
        $this->fieldShowDetail = false;
        $this->fieldShowForm = false;
        $this->fieldShowFilter = false;
        return $this;
    }

    public function onlyOnFilter(): static
    {
        $this->fieldShowList = false;
        $this->fieldShowDetail = false;
        $this->fieldShowForm = false;
        $this->fieldShowFilter = true;
        return $this;
    }

    public function onlyOnDetail(): static
    {
        $this->fieldShowList = false;
        $this->fieldShowDetail = true;
        $this->fieldShowForm = false;
        $this->fieldShowFilter = false;
        return $this;
    }

    public function onlyOnForm(): static
    {
        $this->fieldShowList = false;
        $this->fieldShowDetail = false;
        $this->fieldShowForm = true;
        $this->fieldShowFilter = false;
        return $this;
    }


    /**
     * 设置字段在表单页面是否显示
     * @return $this
     */
    public function hideOnForm(): static
    {
        $this->fieldShowForm = false;
        return $this;
    }

    /**
     * 设置字段在列表页面是否显示
     * @return static
     */
    public function hideOnList(): static
    {
        $this->fieldShowList = false;
        return $this;
    }

    /**
     * 设置字段在详情页面是否显示
     * @return static
     */
    public function hideOnDetail(): static
    {
        $this->fieldShowDetail = false;
        return $this;
    }

    /**
     * 设置字段在列表页面的过滤器中是否显示
     * @return static
     */
    public function hideOnFilter(): static
    {
        $this->fieldShowFilter = false;
        return $this;
    }

}