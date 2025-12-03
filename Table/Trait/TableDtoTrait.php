<?php

namespace Swlib\Table\Trait;


use Exception;
use InvalidArgumentException;
use Swlib\Table\Db;
use Swlib\Utils\Func;
use Throwable;

/**
 * 查询结果的 一条数据相关操作方法
 */
trait TableDtoTrait
{


    private array $__row = [];

    /**
     * @throws Throwable
     */
    public function __fromArray(array $row): void
    {
        foreach ($row as $asName => $v) {
            $this->setByField($asName, $v);
        }
    }

    /**
     * @throws Exception
     */
    public function __toArray(): array
    {
        $ret = [];
        $tableName = self::TABLE_CLASS::TABLE_NAME;
        foreach ($this->getDbFieldNameKeyRow() as $key => $value) {
            $key = Func::camelCaseToUnderscore($key);
            $asName = Db::getFieldAsByName("$tableName.$key", self::TABLE_CLASS::DATABASES);
            $ret[$asName] = $value;
        }
        return $ret;
    }


    /**
     * 返回关联数组
     * key 是 数据库字段名
     * @throws Exception
     */
    public function getDbFieldNameKeyRow(): array
    {
        $result = [];

        foreach (self::TABLE_CLASS::AS_FIELD as $propertyName) {
            if (isset($this->$propertyName)) {
                $result[$propertyName] = $this->$propertyName;
            }
        }

        return $result;
    }


    /**
     * 获取字段的值
     * @throws Throwable
     */
    public function getByField(string $fieldAsName, mixed $def = null): mixed
    {
        // 获取字段的默认值
        $defaultVal = self::TABLE_CLASS::DTO_DEFAULT[$fieldAsName] ?? null;

        // 从别名映射到自身属性，并与默认值进行对比
        try {
            $fieldName = $this->getFieldNameByAs($fieldAsName);
            if ($fieldName && property_exists($this, $fieldName)) {
                $currentVal = $this->$fieldName;

                // 属性值与默认值不相等，认为已设置，直接返回
                if ($currentVal !== $defaultVal) {
                    return $currentVal;
                }
            }
        } catch (Throwable) {
            // 字段是可能不存在的,
            // 可能是自定义查询字段,
            // 例如 join  自己关联自己,就会用到 自定义表别名 和自定义字段
        }


        // 使用 __row 中的值，并与默认值进行对比
        if (array_key_exists($fieldAsName, $this->__row)) {
            $currentVal = $this->__row[$fieldAsName];
            // 属性值与默认值不相等，认为已设置，直接返回
            if ($currentVal !== $defaultVal) {
                return $currentVal;
            }
        }


        if ($def || is_numeric($def)) {
            return $def;
        }

        //  __row 与属性都无法取值 , 也没有给默认值
        if ($defaultVal === null) {
            throw new InvalidArgumentException("$fieldAsName is null ; 请字段是否包含在查询字段中");
        }
        return $defaultVal;
    }

    /**
     * 设置字段的值
     * @throws Throwable
     */
    public function setByField(string $fieldAsName, mixed $value): static
    {
        if (!$value && !is_numeric($value)) {
            // 如果是空值，查看数据库给的默认值是啥
            $value = self::TABLE_CLASS::DTO_DEFAULT[$fieldAsName] ?? null;
        }

        try {
            $fieldName = $this->getFieldNameByAs($fieldAsName);
            if ($fieldName && property_exists($this, $fieldName)) {
                $this->$fieldName = $value;
            }
        } catch (Throwable) {
            // 字段是可能不存在的,
            // 可能是自定义查询字段,
            // 例如 join  自己关联自己,就会用到 自定义表别名 和自定义字段
        }

        $this->__row[$fieldAsName] = $value;
        return $this;
    }


    /**
     * 获取主键值
     * @return int|string
     * @throws Throwable
     */
    public function getPrimaryValue(): int|string
    {
        try {
            return $this->getByField(self::TABLE_CLASS::PRI_KEY);
        } catch (Exception $e) {
            throw new Exception("Failed to get primary value: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 设置主键值
     * @param mixed $v
     * @return  static
     * @throws Throwable
     */
    public function setPrimaryValue(mixed $v): static
    {
        $this->setByField(self::TABLE_CLASS::PRI_KEY, $v);
        return $this;
    }


    /**
     * 获取被修改的属性数据（只包含实际被设置或修改的属性）
     * @return array
     * @throws Throwable
     */
    public function getModifiedData(): array
    {
        $ret = [];

        // 创建一个新的实例作为原始状态
        $originalInstance = new self();

        foreach (static::TABLE_CLASS::FIELD_ALL as $asName) {
            $originalValue = $originalInstance->getByField($asName);
            $currValue = $this->getByField($asName);
            if ($currValue !== $originalValue) {
                $ret[$asName] = $currValue;
            }
        }

        return $ret;
    }

    /**
     * 智能保存数据（自动判断是插入还是更新）
     * @param array $where 更新条件，默认为空，根据主键判断是否更新
     * @return int 受影响的行数或插入的ID
     * @throws Throwable 当操作失败时抛出异常
     */
    public function __save(array $where = []): int
    {
        $className = self::TABLE_CLASS;
        $priKey = self::TABLE_CLASS::PRI_KEY;
        $data = $this->getModifiedData();

        // 如果没有修改的数据，直接返回
        if (empty($data)) {
            return 0;
        }

        if ($where) {
            // 如果 $where 中直接包含主键，优先使用,否则查询主键
            $priValue = $where[$priKey] ?? new $className()->where($where)->selectField($priKey);
        } else if (isset($data[$priKey]) && $id = $data[$priKey]) {
            // 本次修改的数据中有主键
            $priValue = $id;
        } else if ($id = $this->getByField($priKey)) {
            // dto 对象上主键被赋值了
            $priValue = $id;
        }

        if (isset($priValue) && $priValue) {
            $result = new $className()->where([$priKey => $priValue])->update($data);
            // 更新 table id
            $this->setByField($priKey, $priValue);
        } else {
            $id = new $className()->insert($data);
            // 更新 table id
            $this->setByField(self::TABLE_CLASS::PRI_KEY, $id);
            $result = $id;
        }

        return $result;
    }


    /**
     * 根据别名 获取数据库字段名称
     * @param string $fieldAsName
     * @return string|null
     */
    private function getFieldNameByAs(string $fieldAsName): ?string
    {
        if (!isset(self::TABLE_CLASS::AS_FIELD[$fieldAsName])) {
            return null;
        }
        return self::TABLE_CLASS::AS_FIELD[$fieldAsName];
    }


    public function isEmpty(): bool
    {
        if (empty($this->__row) && empty($this->__rows)) {
            return true;
        }
        return false;
    }

    public function __set(string $name, $value): void
    {
        $this->__row[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->__row[$name] ?? null;
    }
}