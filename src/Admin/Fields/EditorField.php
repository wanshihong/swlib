<?php

namespace Swlib\Admin\Fields;


use Swlib\Admin\Manager\AdminManager;

class EditorField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/editor.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/editor.twig";

    public string $url = ""; // 上传的URL

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->url = AdminManager::getInstance()->uploadUrl;
        $this->hideOnFilter();
        $this->addCssFile("/admin/wangeditor/style.css");
        $this->addCssFile("/admin/css/form-editor.css");
        $this->addJsFile("/admin/wangeditor/index.js");
        $this->addJsFile("/admin/js/field-editor.js");
    }


    /**
     * 设置上传图片的地址
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

}