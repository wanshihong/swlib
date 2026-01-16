<?php

namespace Swlib\Controller\Language;


use Swlib\Controller\Abstract\AbstractController;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
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
        $languageTableReflection = Db::getTableReflection('LanguageTable');
        $languages = $languageTableReflection->newInstance()->selectAll();


        $excludeFields = [$languageTableReflection->getConstant('USE_TIME'), $languageTableReflection->getConstant('ID')];

        $ret = [];
        foreach ($languages as $row) {
            foreach ($row as $key => $value) {

                // $key 是字段名称
                // $value 是字段的值
                if (in_array($key, $excludeFields)) {
                    continue;
                }

                $keyName = Db::getFieldNameByAs($key);
                $keyNameArr = explode('.', $keyName);
                $keyName = $keyNameArr[1];
                if (!isset($ret[$keyName])) {
                    $ret[$keyName] = [];
                }
                $ret[$keyName][$row->id] = $value;

            }

        }

        return JsonResponse::success($ret);
    }


}