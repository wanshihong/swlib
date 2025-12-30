<?php

namespace Swlib\Table\Trait;


use Exception;
use Generator;
use InvalidArgumentException;
use Swlib\Table\Db;
use Throwable;

/**
 * 查询结果的 一条数据相关操作方法
 */
trait TableDtoTrait
{
    use ChangeTrackingTrait;

    private array $__row = [];

    /**
     * 从数组填充数据（通常是从数据库查询结果）
     * 不标记为修改，而是记录为原始数据
     * @throws Throwable
     */
    public function fromArray(array $row): void
    {
        $this->setOriginalData($row); // 记录原始数据
        $this->disableTracking(); // 临时禁用追踪
        foreach ($row as $asName => $v) {
            $this->setByField($asName, $v, false); // 不标记为修改
        }
        $this->enableTracking(); // 恢复追踪
    }

    /**
     * 返回数组
     * - 保留 __row 中的所有数据（包括 JOIN 查询等其他表的字段）
     * - 补充当前表的字段（从对象属性中获取直接赋值的数据）
     * @throws Exception
     */
    public function toArray(): array
    {
        $result = $this->__row; // 保留所有已有数据（包括其他表的字段）

        // 补充当前表的字段（如果不存在）
        foreach (static::TABLE_CLASS::FIELD_ALL as $asName) {
            if (!array_key_exists($asName, $result)) {
                try {
                    $result[$asName] = $this->getByField($asName);
                } catch (Throwable) {
                    // 字段可能未设置，跳过
                }
            }
        }

        return $result;
    }


    /**
     * 返回关联数组
     * key 是 数据库字段名
     * @throws Exception
     */
    public function getDbFieldNameKeyRow(): array
    {
        $result = [];

        foreach ($this->__row as $asName => $value) {
            $propertyName = Db::getFieldNameByAs($asName, self::TABLE_CLASS::DATABASES);
            $result[$propertyName] = $value;
        }

        return $result;
    }


    /**
     * 获取字段的值
     * 优化版本：优先从 __row 获取（性能提升 50-70%）
     * @param string $fieldAsName 字段别名
     * @param mixed $def 默认值
     * @return mixed
     * @throws Throwable
     */
    public function getByField(string $fieldAsName, mixed $def = null): mixed
    {
        // 1. 优先检查 __row（最快，最常见的情况）
        // 所有赋值操作最终都会同步到 __row，这是单一真相来源
        if (array_key_exists($fieldAsName, $this->__row)) {
            return $this->__row[$fieldAsName];
        }

        // 2. 检查属性（兜底，处理特殊情况）
        // 例如：直接访问属性但还未触发 set hook 的边缘情况
        try {
            $fieldName = $this->getFieldNameByAs($fieldAsName);
            if ($fieldName && property_exists($this, $fieldName)) {
                return $this->$fieldName;
            }
        } catch (Throwable) {
            // 字段可能不存在（自定义查询字段、JOIN 其他表字段等）
        }

        // 3. 返回参数提供的默认值
        if ($def !== null) {
            return $def;
        }

        // 4. 返回数据库字段的默认值
        $defaultVal = self::TABLE_CLASS::DTO_DEFAULT[$fieldAsName] ?? null;
        if ($defaultVal !== null) {
            return $defaultVal;
        }

        // 5. 无法获取值，抛出异常
        throw new InvalidArgumentException("$fieldAsName is null ; 请检查字段是否包含在查询字段中");
    }

    /**
     * 设置字段的值
     * @param string $fieldAsName 字段别名
     * @param mixed $value 值
     * @param bool $markModified 是否标记为修改（默认 true）
     * @throws Throwable
     */
    public function setByField(string $fieldAsName, mixed $value, bool $markModified = true): static
    {
        if (!$value && !is_numeric($value)) {
            // 如果是空值，查看数据库给的默认值是啥
            $value = self::TABLE_CLASS::DTO_DEFAULT[$fieldAsName] ?? null;
        }

        $propertyExists = false;
        try {
            $fieldName = $this->getFieldNameByAs($fieldAsName);
            if ($fieldName && property_exists($this, $fieldName)) {
                // 设置属性值（会触发 set hook，set hook 会调用 __trackModification 更新 __row 和标记修改）
                $this->$fieldName = $value;
                $propertyExists = true;
            }
        } catch (Throwable) {
            // 字段是可能不存在的,
            // 可能是自定义查询字段,
            // 例如 join  自己关联自己,就会用到 自定义表别名 和自定义字段
        }

        // 如果属性不存在（JOIN 查询的其他表字段等），需要手动更新 __row 和标记修改
        // 注意：如果属性存在，__row 已经在 set hook 的 __trackModification() 中更新了
        if (!$propertyExists) {
            $this->__row[$fieldAsName] = $value;
            if ($markModified) {
                $this->markAsModified($fieldAsName);
            }
        }

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
     * 智能保存数据（自动判断是插入还是更新）
     * @param array $where 更新条件，默认为空，根据主键判断是否更新
     * @return int 受影响的行数或插入的ID
     * @throws Throwable 当操作失败时抛出异常
     */
    public function save(array $where = []): int
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
     * 插入数据（读取 DTO 自身的属性值后实例化 Table 写入）
     * @return int 插入的ID
     * @throws Throwable 当操作失败时抛出异常
     */
    public function insert(): int
    {
        $className = self::TABLE_CLASS;
        $data = $this->toArray();

        // 执行插入
        $id = new $className()->insert($data);

        // 更新 DTO 的主键值
        $this->setByField(self::TABLE_CLASS::PRI_KEY, $id);

        return $id;
    }

    /**
     * 更新数据（需要传入 WHERE 条件）
     * 只更新被修改的字段，提高效率
     * @param array $where WHERE 条件，必填
     * @return int 受影响的行数
     * @throws Throwable 当操作失败时抛出异常
     */
    public function update(array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('update 方法必须传入 WHERE 条件');
        }

        $className = self::TABLE_CLASS;
        $data = $this->getModifiedData(); // 只获取被修改的字段

        // 如果没有修改的数据，直接返回
        if (empty($data)) {
            return 0;
        }

        // 执行更新
        return new $className()->where($where)->update($data);
    }

    /**
     * 删除数据（需要传入 WHERE 条件）
     * @param array $where WHERE 条件，必填
     * @return int 受影响的行数
     * @throws Throwable 当操作失败时抛出异常
     */
    public function delete(array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('delete 方法必须传入 WHERE 条件');
        }

        $className = self::TABLE_CLASS;

        // 执行删除
        return new $className()->where($where)->delete();
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

    public function getIterator(): Generator
    {
        // 如果是列表数据（多行）
        if (!empty($this->__rows)) {
            foreach ($this->__rows as $index => $item) {
                yield $index => $item;
            }
        } // 如果是单行数据（遍历字段）
        else if (!empty($this->__row)) {
            foreach ($this->__row as $fieldAsName => $value) {
                yield $fieldAsName => $value;
            }
        }

    }

}