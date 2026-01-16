<?php

namespace Swlib\Controller\Language;


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
    public function run(): JsonResponse
    {

        $zh = $this->post('zh');

        // 为空 或者 太长了,可能是错误信息 也不必理会
        if (empty($zh) || strlen($zh) > 120) {
            return JsonResponse::success();
        }

        $languageTableReflection = Db::getTableReflection('LanguageTable');
        $id = $languageTableReflection->newInstance()->where([
            $languageTableReflection->getConstant('ZH') => $zh,
        ])->selectField($languageTableReflection->getConstant('ID'));

        if (empty($id)) {
            $languageTableReflection->newInstance()->insert([
                $languageTableReflection->getConstant('ZH') => $zh,
                $languageTableReflection->getConstant('USE_TIME') => time(),
            ]);
        } else {
            $languageTableReflection->newInstance()->where([
                $languageTableReflection->getConstant('ID') => $id,
            ])->update([
                $languageTableReflection->getConstant('USE_TIME') => time(),
            ]);
        }


        return JsonResponse::success();

    }


}