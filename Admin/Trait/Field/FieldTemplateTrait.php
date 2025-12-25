<?php

namespace Swlib\Admin\Trait\Field;

trait FieldTemplateTrait
{

    // 列表页面自定义模板
    public string $templateList;

    // 列表页面自定义模板
    public string $templateForm;
    public string $templateFilter = "fields/filter/text.twig";


    // 列表字段最大长度
    public int $listMaxWidth = 150;

    // 是否禁用控件，用户不可操作 添加 disabled 属性
    public bool $disabled = false;


    public bool $tooltip = true;


    /**
     * 设置列表一列的最大宽度
     * @param int $listMaxWidth
     * @return $this
     */
    public function setListMaxWidth(int $listMaxWidth): static
    {
        $this->listMaxWidth = $listMaxWidth;
        return $this;
    }


    /**
     * 是否禁用控件，禁用后用户不可操作
     * 添加 disabled 属性
     * @param bool $disabled
     * @return $this
     */
    public function setDisabled(bool $disabled): static
    {
        $this->disabled = $disabled;
        return $this;
    }


    /**
     * 设置列表页面自定义模板
     * @param string $template
     * @return $this
     */
    public function setTemplateList(string $template): static
    {
        $this->templateList = $template;
        return $this;
    }

    /**
     * 设置表单页面自定义模板
     * @param string $template
     * @return $this
     */
    public function setTemplateForm(string $template): static
    {
        $this->templateForm = $template;
        return $this;
    }

    /**
     * 设置过滤器页面自定义模板
     * @param string $template
     * @return $this
     */
    public function setTemplateFilter(string $template): static
    {
        $this->templateFilter = $template;
        return $this;
    }

    public function setTooltip(bool $tooltip): static
    {
        $this->tooltip = $tooltip;
        return $this;
    }

}