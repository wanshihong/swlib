<?php

namespace Swlib\Controller\Language;


use Generate\Tables\Main\LanguageTable;
use Protobuf\Main\Language\LanguageProto;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
use Throwable;


class SaveAndUse extends AbstractController
{

    /**
     * 设置翻译的使用时间，长时间未使用的可以删除
     * @throws Throwable
     */
    #[Router(method: 'POST', errorTitle: '设置使用时间失败')]
    public function run(LanguageProto $request): JsonResponse
    {

        $zh = $request->getZh();

        // 为空 或者 太长了,可能是错误信息 也不必理会
        if (empty($zh) || strlen($zh) > 120) {
            return JsonResponse::success();
        }

        $id = new LanguageTable()->where([
            LanguageTable::ZH => $zh,
        ])->selectField(LanguageTable::ID);

        if (empty($id)) {
            new LanguageTable()->insert([
                LanguageTable::ZH => $zh,
                LanguageTable::USE_TIME => time(),
            ]);
        } else {
            new LanguageTable()->where([
                LanguageTable::ID => $id,
            ])->update([
                LanguageTable::USE_TIME => time(),
            ]);
        }


        return JsonResponse::success();

    }


}