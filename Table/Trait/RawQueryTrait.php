<?php

namespace Swlib\Table\Trait;

use InvalidArgumentException;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Table\Db;
use Swlib\Table\Interface\TableDtoInterface;
use Swlib\Utils\StringConverter;
use Swlib\Utils\TypeHelper;
use Throwable;

/**
 * 原生查询转换 Trait
 *
 * 提供原生 SQL 查询并自动转换为对象的功能
 *
 * 使用场景：
 * - 需要执行复杂的原生 SQL 查询
 * - 希望查询结果自动转换为对应的 Table 对象
 * - 保持 ORM 的便利性同时获得原生查询的灵活性
 *
 * 使用示例：
 * ```php
 * class OrderTable extends BaseTable
 * {
 *     use RawQueryTrait;
 *
 *     // ... 其他代码
 * }
 *
 * // 使用原生查询
 * $orderTable = new OrderTable();
 *
 * // 查询单个对象
 * $order = $orderTable->queryToObject("SELECT * FROM orders WHERE id = ?", [123]);
 *
 * // 查询对象列表
 * $orders = $orderTable->queryToObjects("SELECT * FROM orders WHERE user_id = ? LIMIT 10", [456]);
 * ```
 */
trait RawQueryTrait
{
    /**
     * 执行原生 SQL 查询并转换为单个对象
     *
     * @param string $sql SQL 查询语句
     * @param array $params 查询参数
     * @return static|null 转换后的对象，如果没有结果则返回 null
     * @throws InvalidArgumentException|Throwable 当查询结果格式不正确时抛出异常
     */
    public function queryToObject(string $sql, array $params = []): ?TableDtoInterface
    {
        $result = Db::query($sql, $params);

        if (empty($result)) {
            return null;
        }

        // 如果结果是多行，只取第一行
        $rawData = is_array($result[0] ?? null) ? $result[0] : $result;

        if (!is_array($rawData)) {
            throw new AppException(AppErr::DB_QUERY_RESULT_INVALID);
        }

        return $this->_convertSingleRawDataToObject($rawData);
    }

    /**
     * 执行原生 SQL 查询并转换为对象数组
     *
     * @param string $sql SQL 查询语句
     * @param array $params 查询参数
     * @return TableDtoInterface[] 转换后的对象数组
     * @throws InvalidArgumentException 当查询结果格式不正确时抛出异常
     * @throws Throwable
     */
    public function queryToObjects(string $sql, array $params = []): array
    {
        $result = Db::query($sql, $params);

        if (empty($result)) {
            return [];
        }

        $objects = [];
        foreach ($result as $rawData) {
            if (!is_array($rawData)) {
                throw new AppException(AppErr::DB_QUERY_ROW_MUST_BE_ARRAY);
            }
            $objects[] = $this->_convertSingleRawDataToObject($rawData);
        }

        return $objects;
    }

    /**
     * 将单个原生查询结果转换为 Table 对象
     *
     * 此方法将数据库字段名（下划线格式）转换为对象属性名（小驼峰格式）
     * 并安全地设置属性值，处理类型不匹配的问题
     *
     * @param array $rawData 单个原生查询结果（关联数组）
     * @return TableDtoInterface 转换后的对象
     */
    private function _convertSingleRawDataToObject(array $rawData): TableDtoInterface
    {
        $object = $this->getDto();

        // 使用 Func 类的批量转换方法，将所有字段名从下划线格式转换为小驼峰格式
        $camelCaseData = StringConverter::convertArrayKeysToCamelCase($rawData);

        // 将转换后的数据设置到对象属性中
        foreach ($camelCaseData as $property => $value) {
            // 使用 TypeHelper 安全地设置属性值
            TypeHelper::safeSetProperty($object, $property, $value);
        }

        return $object;
    }
} 