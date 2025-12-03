<?php

namespace Swlib\Admin\Action;

/**
 * 点击后执行某个行为，并且获取进度
 */
class UploadAction extends ProgressAction
{


    public function __construct(public string $label, public string $url, public array $params = [])
    {
        parent::__construct($label, $url, $params);
        $this->addAttribute('action-type', 'upload');
    }


}