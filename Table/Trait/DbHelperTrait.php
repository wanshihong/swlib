<?php

namespace Swlib\Table\Trait;

use Exception;
use Generate\DatabaseConnect;
use Generate\TableFieldMap;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Table\Expression;
use Throwable;

trait DbHelperTrait
{

    /**
     * 根据数据库字段别名获取字段名称
     * @param string $fieldAs
     * @param string $dbName
     * @return string
     * @throws Exception
     */
    public static function getFieldNameByAs(string $fieldAs, string $dbName = 'default'): string
    {
        try {
            // 优先进行查找返回，因为这个频率是最高的
            return self::_getFieldNameByAs($fieldAs, $dbName);
        } catch (Throwable) {
            // 没有找到，证明有特殊的操作
            throw new AppException(AppErr::DB_FIELD_NOT_FOUND_IN_ALIAS . ": $fieldAs");
        }

    }

    /**
     * 根据字段别名获取 数据库字段名
     * @throws Exception
     */
    private static function _getFieldNameByAs(string $fieldAs, string $dbName = 'default'): string
    {
        $dbName = DatabaseConnect::getDbName($dbName);
        if (!isset(TableFieldMap::maps[$dbName][$fieldAs])) {
            throw new AppException(AppErr::DB_FIELD_NOT_FOUND_IN_DEFINITION . ": $fieldAs");
        }
        return TableFieldMap::maps[$dbName][$fieldAs];
    }


    /**
     * 根据字段名称获取数据库字段别名
     * @throws Exception
     */
    public static function getFieldAsByName(string $fieldName, string $dbName = 'default'): string
    {
        $dbName = DatabaseConnect::getDbName($dbName);
        $res = array_search($fieldName, TableFieldMap::maps[$dbName]);
        if (empty($res)) {
            throw new AppException(AppErr::DB_FIELD_NOT_FOUND_IN_ALIAS . ": $fieldName");
        }
        return (string)$res;
    }


    /**
     * 检查字段是否存在
     * @param string $fieldName
     * @param string $dbName
     * @return bool
     */
    public static function checkFieldExists(string $fieldName, string $dbName = 'default'): bool
    {
        return in_array($fieldName, TableFieldMap::maps[$dbName]);
    }

    /**
     * 检查别名是否存在
     * @param string $asName
     * @param string $dbName
     * @return bool
     */
    public static function checkAsExists(string $asName, string $dbName = 'default'): bool
    {
        $dbName = DatabaseConnect::getDbName($dbName);
        return isset(TableFieldMap::maps[$dbName][$asName]);
    }


    /**
     * 生成 update 增量字段 sql
     * 用户也可以手动拼接，只是调用函数减少出错概率
     * @param int|float $value 需要增量的 值
     * @param string $operator 运算符符号  +  - *  /
     *
     * 示例 ：
     * new Table()->where([
     *     Table::ID => 25
     * ])->update([
     *     Table::TIME => Db::incr(Table::TIME, 1)
     * ]);
     *
     * @return Expression
     */
    public static function incr(int|float $value = 1, string $operator = '+'): Expression
    {
        return new Expression("`FIELD` $operator $value");
    }

    /**
     * 直接使用 SQL 语句设置查询字段
     *
     * 使用示例
     * $lists = new ChatMessagesTable()->field([
     *      ChatMessagesTable::SESSION_ID,
     *      Db::raw('COUNT(*) as unread_count'),
     *      Db::raw('\'avc\' as t2'),
     * ])->where([
     *      [ChatMessagesTable::IS_READ, '=', 0],
     *      [ChatMessagesTable::DELETED_AT, 'IS NULL', 0],
     * ])->group(ChatMessagesTable::SESSION_ID)->selectIterator();
     *
     * foreach ($lists as $item) {
     *      echo $item->sessionId . PHP_EOL;;
     *      echo $item->getByField('unread_count') . PHP_EOL;;
     *      echo $item->getByField('t2') . PHP_EOL;;
     * }
     *
     * @param string $sqlField
     * @return Expression
     */
    public static function raw(string $sqlField): Expression
    {
        return new Expression($sqlField);
    }
}