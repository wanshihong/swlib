<?php

namespace Swlib\Admin\Action\Enum;

enum ActionPosEnum: string
{

    case  LISTS_PAGE = 'lists_page'; // 列表页面
    case LISTS_PAGE_ROW = 'lists_page_row'; // 列表页面 表格中的每一行
    case NEW_PAGE = 'new_page';// 添加页面
    case EDIT_PAGE = 'edit_page';// 编辑页面
    case DETAIL_PAGE = 'detail_page'; // 详情页面


}
