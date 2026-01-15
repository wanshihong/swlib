<?php

namespace Swlib\Admin\Fields;

use Generate\RouterPath;
use Swlib\Admin\Manager\AdminManager;

class ImageField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/image.vue";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/image.vue";


    public string $url = ""; // 上传的URL
    public string $name = "file"; // 上传的 post key
    public string $accept = "image/*"; // 文件类型
    public int $max = 1; // 上传文件数量

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $uploadUrl = AdminManager::getInstance()->uploadUrl;
        if (empty($uploadUrl)) {
            $uploadUrl = RouterPath::FileUpload;
        }
        $this->url = $uploadUrl;
        $this->addJsFile('/admin/js/field-image.js');
        $this->addCssFile('/admin/css/field-image.css');
        $this->hideOnFilter();
    }

    /**
     * 设置文件上传的URL
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url): ImageField
    {
        $this->url = $url;
        return $this;
    }


    /**
     * 设置文件上传的 POST KEY
     * @param string $name
     * @return $this
     */
    public function setName(string $name): ImageField
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 设置文件上传的 ACCEPT
     * @param string $accept
     * @return $this
     */
    public function setAccept(string $accept): ImageField
    {
        $this->accept = $accept;
        return $this;
    }

    /**
     * 设置最大上传数量
     * @param int $max
     * @return $this
     */
    public function setMax(int $max): ImageField
    {
        $this->max = $max;
        return $this;
    }

}