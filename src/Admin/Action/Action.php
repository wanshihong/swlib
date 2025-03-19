<?php

namespace Swlib\Admin\Action;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Trait\AttrTrait;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Admin\Trait\StaticTrait;
use Swlib\Utils\Language;
use Throwable;

class Action implements PermissionInterface
{
    use PermissionTrait;
    use StaticTrait;
    use AttrTrait;

    public bool $showOnList = false;  // 是否显示在表格中的每一行显示
    public bool $showOnIndex = false;// 是否再首页显示，也就是首页的添加按钮旁
    public bool $showOnFormNew = false;
    public bool $showOnFormEdit = false;
    public bool $showOnDetail = false;

    // 点击按钮以后的跳转方式，默认本窗口，可选 _blank _parent _top
    public string $target = '_self';

    public int $sort = 0;

    public string $template = 'action/action-alink.twig';

    // 按钮图标  去这里找 https://icons.getbootstrap.com/
    public string $icon = '';


    /**
     * @throws Throwable
     */
    public function __construct(public string $label, public string $url, public array $params = [])
    {
        $this->label = Language::get($label);
    }


    public function addParams(array|null $params = []): static
    {
        if ($params) {
            $this->params = array_merge($this->params, $params);
        }
        return $this;
    }


    public function onlyIndex(): static
    {
        $this->showOnIndex = true;
        $this->showOnList = false;
        $this->showOnDetail = false;
        $this->showOnFormNew = false;
        $this->showOnFormEdit = false;
        return $this;
    }

    public function onlyList(): static
    {
        $this->showOnIndex = false;
        $this->showOnList = true;
        $this->showOnDetail = false;
        $this->showOnFormNew = false;
        $this->showOnFormEdit = false;
        return $this;
    }

    public function onlyForm(): static
    {
        $this->showOnIndex = false;
        $this->showOnList = false;
        $this->showOnDetail = false;
        $this->showOnFormNew = false;
        $this->showOnFormEdit = false;
        return $this;
    }

    public function onlyFormNew(): static
    {
        $this->showOnIndex = false;
        $this->showOnList = false;
        $this->showOnFormNew = true;
        $this->showOnFormEdit = false;
        $this->showOnDetail = false;
        return $this;
    }

    public function onlyFormEdit(): static
    {
        $this->showOnIndex = false;
        $this->showOnList = false;
        $this->showOnFormNew = false;
        $this->showOnFormEdit = true;
        $this->showOnDetail = false;
        return $this;
    }

    public function onlyDetail(): static
    {
        $this->showOnIndex = false;
        $this->showOnList = false;
        $this->showOnDetail = true;
        $this->showOnFormNew = false;
        $this->showOnFormEdit = false;
        return $this;
    }


    /**
     * 在首页显示，列表页面的添加旁
     * @return $this
     */
    public function showIndex(): static
    {
        $this->showOnIndex = true;
        return $this;
    }

    /**
     * 在首页表格中的每一行显示
     * @return $this
     */
    public function showList(): static
    {
        $this->showOnList = true;
        return $this;
    }


    public function showFormNew(): static
    {
        $this->showOnFormNew = true;
        return $this;
    }

    public function showFormEdit(): static
    {
        $this->showOnFormEdit = true;
        return $this;
    }

    public function showDetail(): static
    {
        $this->showOnDetail = true;
        return $this;
    }

    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * 设置操作排序,数字越大越靠后
     * @param int $sort
     * @return $this
     */
    public function setSort(int $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * 点击按钮以后的跳转方式，默认本窗口，
     * 可选 _blank _parent _top
     * @param string $target
     * @return $this
     */
    public function setTarget(string $target): Action
    {
        $this->target = $target;
        return $this;
    }

}