<?php

namespace Swlib\Admin\Action;

use Swlib\Admin\Utils\Func;

/**
 * 点击后执行某个行为，并且获取进度
 */
class ProgressAction extends Action
{


    public function __construct(public string $label, public string $url, public array $params)
    {
        parent::__construct($label, 'javascript:');
        $this->addClass('progress_btn');
        $this->addAttribute('run-url', Func::url($url, $params));
        $this->showIndex();
        $this->addJsFile('/admin/js/progress_btn.js');
    }


    /**
     * 获取进度的url
     * @param string $url
     * @return $this
     */
    public function setProgressUrl(string $url): static
    {
        $this->addAttribute('get-progress-url', $url);
        return $this;
    }

    /**
     * 完成后跳转的URL，为空就不跳转
     * @param string $url
     * @return $this
     */
    public function setCompleteUrl(string $url): static
    {
        $this->addAttribute('complete-url', $url);
        return $this;
    }


}