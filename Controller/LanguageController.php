<?php

namespace Swlib\Controller;


use Generate\Tables\MiYaoBiJi\LanguageTable;
use Protobuf\Common\Success;
use Protobuf\MiYaoBiJi\Language\LanguageProto;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
use Throwable;


class LanguageController extends AbstractController
{


    /**
     * 这是查询翻译列表，返回所有的翻译配置
     * @throws Throwable
     */
    #[Router(method: ['GET', 'POST'], errorTitle: '获取翻译列表数据失败')]
    public function all(): JsonResponse
    {
        $languages = new  LanguageTable()->selectAll();
        $ret = [];
        foreach ($languages as $row) {
            foreach ($row as $key => $value) {

                // $key 是字段名称
                // $value 是字段的值
                if (in_array($key, [LanguageTable::USE_TIME, LanguageTable::ID])) {
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


    /**
     * 设置翻译的使用时间，长时间未使用的可以删除
     * @throws Throwable
     */
    #[Router(method: 'POST', errorTitle: '设置使用时间失败')]
    public function saveAndUse(LanguageProto $request): Success
    {
        $zh = $request->getZh();
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

        $msg = new Success();
        $msg->setSuccess(true);
        return $msg;

    }


}