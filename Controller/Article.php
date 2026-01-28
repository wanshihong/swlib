<?php

namespace Swlib\Controller;


use Generate\Models\Main\ArticleModel;
use Generate\Tables\Main\ArticleTable;
use Protobuf\Main\Article\ArticleListsProto;
use Protobuf\Main\Article\ArticleProto;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Router\Router;
use Throwable;


/*
* 文章信息表
*/

#[Router(method: 'POST')]
class Article extends AbstractController
{


    /**
     * @throws Throwable
     */
    #[Router(errorTitle: '获取文章信息表列表数据失败')]
    public function lists(ArticleProto $request): ArticleListsProto
    {
        $page = $request->getPageNumber() ?: 1;
        $size = $request->getPageSize() ?: 10;
        $pos = $request->getGroupPos();

        if (empty($appId)) {
            throw new AppException(AppErr::PARAM_REQUIRED);
        }
        if (empty($pos)) {
            throw new AppException(AppErr::PARAM_REQUIRED);
        }

        $where = [
            [ArticleTable::GROUP_POS, 'like', "%$pos%"],
        ];
        $order = [ArticleTable::PRI_KEY => "desc"];
        $articleTable = new ArticleTable();
        $lists = $articleTable->order($order)->field([
            ArticleTable::ID,
            ArticleTable::TITLE,
            ArticleTable::SUB_TITLE,
            ArticleTable::COVER,
        ])->where($where)->page($page, $size)->selectAll();


        $protoLists = [];
        foreach ($lists as $table) {
            $proto = ArticleModel::formatItem($table);
            // 其他自定义字段格式化
            $protoLists[] = $proto;
        }

        $ret = new ArticleListsProto();
        $ret->setLists($protoLists);
        return $ret;
    }



    /**
     * @throws Throwable
     */
    #[Router(errorTitle: '查看文章信息表详情失败')]
    public function detail(ArticleProto $request): ArticleProto
    {
        $id = $request->getId();
        if (empty($id)) {
            throw new AppException(AppErr::PARAM_REQUIRED);
        }

        $table = new ArticleTable()->where([
            ArticleTable::ID => $id,
        ])->selectOne();
        if (empty($table)) {
            throw new AppException(AppErr::NOT_FOUND);
        }

        return ArticleModel::formatItem($table);
    }

}