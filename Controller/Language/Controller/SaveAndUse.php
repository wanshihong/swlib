<?php

namespace Swlib\Controller\Language\Controller;


use Generate\Tables\Main\LanguageTable;
use Protobuf\Common\Success;
use Protobuf\Main\Language\LanguageProto;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Router\Router;
use Throwable;


class SaveAndUse extends AbstractController
{

    /**
     * 设置翻译的使用时间，长时间未使用的可以删除
     * @throws Throwable
     */
    #[Router(method: 'POST', errorTitle: '设置使用时间失败')]
    public function run(LanguageProto $request): Success
    {

        $key = $request->getKey();

        $ret = new Success();
        $ret->setSuccess(true);

        // 为空 或者 太长了,可能是错误信息 也不必理会
        if (empty($key) || strlen($key) > 120) {
            return $ret;
        }

        $id = new LanguageTable()->where([
            LanguageTable::KEY => $key,
        ])->selectField(LanguageTable::ID);

        if (empty($id)) {
            new LanguageTable()->insert([
                LanguageTable::KEY => $key,
                LanguageTable::USE_TIME => time(),
            ]);
        } else {
            new LanguageTable()->where([
                LanguageTable::ID => $id,
            ])->update([
                LanguageTable::USE_TIME => time(),
            ]);
        }

        return $ret;

    }


}