<?php

namespace Swlib\Controller;


use Exception;
use Protobuf\Common\Success;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
use Swlib\DataManager\ReflectionManager;
use Swlib\Connect\PoolMysql;
use Swlib\Utils\StringConverter;
use Throwable;
use ReflectionException;


class LanguageController extends AbstractController
{
    /**
     * 动态获取 LanguageTable 类
     * @throws ReflectionException
     * @throws Exception
     */
    private static function getLanguageTableClass(): string
    {
        $tableReflection = Db::getTableReflection('LanguageTable');
        return $tableReflection->getName();
    }

    /**
     * 动态获取 LanguageProto 类
     * @throws ReflectionException
     * @throws Exception
     */
    private static function getLanguageProtoClass(): string
    {
        $dbName = PoolMysql::getDbName();
        $dbName = StringConverter::underscoreToCamelCase($dbName);
        $protoClassName = "Protobuf\\$dbName\\Language\\LanguageProto";

        // 验证类是否存在
        ReflectionManager::getClass($protoClassName);
        return $protoClassName;
    }

    /**
     * 这是查询翻译列表，返回所有的翻译配置
     * @throws Throwable
     */
    #[Router(method: ['GET', 'POST'], errorTitle: '获取翻译列表数据失败')]
    public function all(): JsonResponse
    {
        $tableClass = self::getLanguageTableClass();
        $languages = new $tableClass()->selectAll();

        // 提前获取反射和常量，避免在循环中重复获取
        $tableReflection = ReflectionManager::getClass($tableClass);
        $useTimeConstant = $tableReflection->getConstant('USE_TIME');
        $idConstant = $tableReflection->getConstant('ID');
        $excludeFields = [$useTimeConstant, $idConstant];

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


    /**
     * 设置翻译的使用时间，长时间未使用的可以删除
     * @param object $request LanguageProto 实例
     * @throws Throwable
     */
    #[Router(method: 'POST', errorTitle: '设置使用时间失败')]
    public function saveAndUse(object $request): Success
    {
        $protoClass = self::getLanguageProtoClass();
        $tableClass = self::getLanguageTableClass();

        // 确保 $request 是正确的 Proto 类型
        if (!($request instanceof $protoClass)) {
            throw new Exception("Invalid request type: expected $protoClass");
        }

        $zh = $request->getZh();

        $msg = new Success();
        $msg->setSuccess(true);
        if (empty($zh)) {
            return $msg;
        }

        // 太长了,可能是错误信息 也不必理会
        if (strlen($zh) > 120) {
            return $msg;
        }

        // 使用反射获取常量值
        $tableReflection = ReflectionManager::getClass($tableClass);
        $zhConstant = $tableReflection->getConstant('ZH');
        $idConstant = $tableReflection->getConstant('ID');
        $useTimeConstant = $tableReflection->getConstant('USE_TIME');

        $id = new $tableClass()->where([
            $zhConstant => $zh,
        ])->selectField($idConstant);

        if (empty($id)) {
            new $tableClass()->insert([
                $zhConstant => $zh,
                $useTimeConstant => time(),
            ]);
        } else {
            new $tableClass()->where([
                $idConstant => $id,
            ])->update([
                $useTimeConstant => time(),
            ]);
        }


        return $msg;

    }


}