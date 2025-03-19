<?php

namespace Swlib\Admin\Trait;

use Exception;
use Swlib\Admin\Action\Action;
use Swlib\Admin\Enum\ActionDefaultButtonEnum;
use Swlib\Admin\Enum\PagePosEnum;
use Swlib\Admin\Manager\AdminUserManager;
use Throwable;

trait ActionTrait
{
    public ?Action $formSaveAction = null;
    public ?Action $listAddAction = null;
    public ?Action $listEditAction = null;
    public ?Action $listDetailAction = null;
    public ?Action $listDeleteAction = null;


    private array $_getActionsCache = [];

    /**
     * @throws Exception
     */
    public function createDefaultAction(): static
    {
        // 表单页面 保存按钮
        $this->formSaveAction = new Action(label: "保存", url: 'new')->setSort(0)
            ->showFormNew()->showFormEdit()->setIcon('bi bi-floppy')
            ->setTemplate('action/action-btn-submit.twig');
        $this->actions[] = $this->formSaveAction;


        // 如果添加没有被禁用
        if (!in_array(ActionDefaultButtonEnum::NEW, $this->disabledActions)) {
            $this->listAddAction = new Action(label: "添加", url: 'new')->showIndex()->setSort(0)
                ->setTemplate('action/action-alink.twig')->setIcon('bi bi-plus');
            $this->actions[] = $this->listAddAction;
        }

        // 如果编辑没有被禁用
        if (!in_array(ActionDefaultButtonEnum::EDIT, $this->disabledActions)) {
            $this->listEditAction = new Action(label: '编辑', url: 'edit')->showList()->showDetail()->setSort(1)
                ->setTemplate('action/action-alink.twig')->setIcon('bi bi-pencil');
            $this->actions[] = $this->listEditAction;
        }

        // 如果详情没有被禁用
        if (!in_array(ActionDefaultButtonEnum::DETAIL, $this->disabledActions)) {
            $this->listDetailAction = new Action(label: '详情', url: 'detail')->onlyList()->setSort(2)
                ->setTemplate('action/action-alink.twig')->setIcon('bi bi-body-text');
            $this->actions[] = $this->listDetailAction;
        }

        // 如果删除没有被禁用
        if (!in_array(ActionDefaultButtonEnum::DELETE, $this->disabledActions)) {
            $this->listDeleteAction = new Action(label: '删除', url: 'delete')->addJsFile('/admin/js/action-delete.js')
                ->showList()->showDetail()->showFormEdit()->setSort(3)
                ->setTemplate('action/action-alink-delete.twig')->setIcon('bi bi-trash');
            $this->actions[] = $this->listDeleteAction;
        }

        // 二维数组排序，对按钮排序
        usort($this->actions, function ($a, $b) {
            return $a->sort - $b->sort;
        });

        return $this;
    }


    /**
     * @throws Throwable
     */
    public function getActionByPos(PagePosEnum $pos): array
    {
        $ret = [];
        foreach ($this->getActions() as $action) {
            switch ($pos) {
                case PagePosEnum::INDEX_ADD:
                    if ($action->showOnIndex) {
                        $ret[] = $action;
                    }
                    break;
                case PagePosEnum::INDEX_LISTS :
                    if ($action->showOnList) {
                        $ret[] = $action;
                    }
                    break;
                case PagePosEnum::FORM_NEW :
                    if ($action->showOnFormNew) {
                        $ret[] = $action;
                    }
                    break;
                case PagePosEnum::FORM_EDIT :
                    if ($action->showOnFormEdit) {
                        $ret[] = $action;
                    }
                    break;
                case PagePosEnum::DETAIL :
                    if ($action->showOnDetail) {
                        $ret[] = $action;
                    }
                    break;
                case PagePosEnum::DELETE:
                    throw new Exception('To be implemented');
                case PagePosEnum::SWITCH:
                    throw new Exception('To be implemented');
                case PagePosEnum::GET_SELECT_LIST:
                    throw new Exception('To be implemented');
            }
        }

        return $ret;
    }

    /**
     * 判断这个操作是否有权限
     * @throws Throwable
     */
    public function frameworkCheckFieldsPermissions(): array
    {
        $ret = [];
        foreach ($this->actions as $action) {
            if (AdminUserManager::checkPermissionsByConfig($action) === false) {
                continue;
            }
            $ret[] = $action;
        }
        $this->actions = $ret;
        return $ret;
    }

    /**
     * 获取操作, 并过滤掉没有权限的
     * 这个方法在模板渲染中调用过
     * @return  Action[]
     * @throws Throwable
     */
    public function getActions(): array
    {
        if ($this->_getActionsCache) {
            return $this->_getActionsCache;
        }
        $res = $this->frameworkCheckFieldsPermissions();
        $this->_getActionsCache = $res;
        return $res;
    }

}