<?php

namespace Swlib\Admin\Manager;

class OptionManager
{
    public function __construct(
        // 选项的唯一标识
        public int|string $id,

        // 选项的展示内容
        public int|string $text,

        // 当前选项是否选中
        public bool       $checked = false
    )
    {
    }
}