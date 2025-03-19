<?php

namespace Swlib\Admin\Config;


use Swlib\Admin\Action\Action;
use Swlib\Admin\Enum\ActionDefaultButtonEnum;
use Swlib\Admin\Trait\ActionTrait;


class ActionsConfig
{

    use ActionTrait;


    /**
     * @var  Action[]
     */
    public array $actions = [];

    /**
     * 禁用的操作按钮
     * @var ActionDefaultButtonEnum[]
     */
    public array $disabledActions = [];


    /**
     * @param Action ...$actions
     * @return $this
     */
    public function addActions(Action ...$actions): self
    {
        array_push($this->actions, ...$actions);
        return $this;
    }


}