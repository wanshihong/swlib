<?php

namespace Swlib\Admin\Enum;

enum PagePosEnum: string
{
    /**
     * 首页的添加按钮旁边
     */
    case INDEX_ADD = 'index-add';


    /**
     * 首页表格中的每一行
     */
    case INDEX_LISTS = 'index-lists';


    /**
     * 添加页面
     */
    case FORM_NEW = 'form-new';


    /**
     * 编辑页面
     */
    case FORM_EDIT = 'form-edit';

    /**
     * 详情页面
     */
    case DETAIL = 'detail';


    /**
     * 删除操作
     */
    case DELETE = 'delete';


    /**
     * 切换字段操作
     */
    case SWITCH = 'switch';


    /**
     * 切换字段操作
     */
    case GET_SELECT_LIST = 'getSelectList';


}
