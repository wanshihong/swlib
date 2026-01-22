<?php

namespace Swlib\Controller\Language;


use Generate\Models\Main\LanguageModel;
use Generate\Tables\Main\LanguageTable;
use Protobuf\Main\Language\LanguageListsProto;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Throwable;


class All extends AbstractController
{

    /**
     * 这是查询翻译列表，返回所有的翻译配置
     * @throws Throwable
     */
    #[Router(method: ['GET', 'POST'], errorTitle: '获取翻译列表数据失败')]
    public function run(): JsonResponse
    {
        $languages = new LanguageTable()->selectAll();

        $nodes = [];
        foreach ($languages as $row) {
            $nodes[] = LanguageModel::formatItem($row);
        }
        $ret = new LanguageListsProto();
        $ret->setLists($nodes);

        return JsonResponse::success($ret);
    }


}