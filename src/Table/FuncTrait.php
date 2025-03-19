<?php

namespace Swlib\Table;


use Exception;
use Generator;
use InvalidArgumentException;
use Swlib\Connect\PoolRedis;
use Redis;
use Throwable;

trait FuncTrait
{

    /**
     * 当前实例的一行数据
     * @var array
     */
    public array $_row = [];
    protected array $_rows = [];

    protected bool $debugSql = false;


    /**
     * 获取函数计算的字段,这里仅支持没有 mysql 参数的函数计算方法
     * @param string $field 需要计算的字段名称
     * @param string $func 需要计算的  mysql 函数  count  sum  min  max  avg .....
     * @return string
     */
    public static function getFuncField(string $field, string $func): string
    {
        $delimiter = TableEnum::FUNCTION_FIELD_DELIMITER->value;
        return "$field$delimiter$func";
    }

    public function inField(string $field): bool
    {
        return in_array($field, static::FIELD_ALL);
    }

    public function __toArray(): array
    {
        return $this->_row;
    }


    /**
     * 设置数据
     * @param array $data
     * @return static
     */
    public function __fromArray(array $data): static
    {
        foreach ($data as $key => $v) {
            $this->_row[$key] = $v;
        }
        return $this;
    }

    /**
     * 获取字段的值
     * @throws Throwable
     */
    public function getByField(string $field, mixed $def = null): mixed
    {
        if (array_key_exists($field, $this->_row)) {
            $ret = $this->_row[$field];
            if ($ret === null && $def !== null) {
                return $def;
            }
            return $ret;
        }
        if ($def === null) {
            throw new InvalidArgumentException("$field is null");
        }
        return $def;
    }

    /**
     * 设置字段的值
     * @throws Throwable
     */
    public function setByField(string $field, mixed $value): static
    {
        if ($value) {
            $this->_row[$field] = $value;
        } else {
            // 如果是空值，查看数据库给的默认值是啥
            $this->_row[$field] = static::INSERT_UPDATE_DEFAULT[$field] ?? null;
        }

        return $this;
    }


    /**
     * 获取主键字段
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return static::PRI_KEY;
    }

    /**
     * 获取主键字段原始名称
     * @return string
     * @throws Exception
     */
    public function getPrimaryKeyOriginal(): string
    {
        return explode('.', Db::getFieldNameByAs(static::PRI_KEY, self::DATABASES))[1];
    }

    /**
     * 获取主键值
     * @return int|string
     * @throws Throwable
     */
    public function getPrimaryValue(): int|string
    {
        try {
            return $this->getByField(static::PRI_KEY);
        } catch (Exception $e) {
            throw new Exception("Failed to get primary value: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取主键值
     * @param mixed $v
     * @return  static
     * @throws Throwable
     */
    public function setPrimaryValue(mixed $v): static
    {
        $this->setByField(static::PRI_KEY, $v);
        return $this;
    }


    /**
     *  getAll 返回的是对象数组
     *  select 返回是一个二维数组
     * @return static[]
     * @throws Throwable
     */
    public function selectAll(): array
    {
        if ($this->_rows) {
            return $this->_rows;
        }

        $ret = [];
        foreach ($this->select() as $item) {
            $table = new static();
            $table->__fromArray($item);
            $ret[] = $table;
        }
        $this->_rows = $ret;
        return $ret;
    }

    /**
     * 返回一个生成器
     * 查询不会用到缓存，如果需要使用缓存请使用 getAll
     * @throws Throwable
     */
    public function generator(): Generator
    {
        try {
            foreach (static::getIterator() as $item) {
                $table = new static();
                $table->__fromArray($item);
                yield $table;
            }
        } finally {
            $this->close();
        }
    }

    /**
     * getOne 返回的是对象
     * find   返回是一个数组
     * @return static|null
     * @throws Throwable
     */
    public function selectOne(): ?static
    {
        $data = $this->find();
        if (empty($data)) {
            return null;
        }
        $this->__fromArray($data);
        return $this;
    }

    /**
     * @throws Throwable
     */
    public function selectField(string $field, $default = null): mixed
    {
        $this->field($field);
        $res = $this->find();
        return $res ? $res[$field] : $default;
    }


    /**
     * 获取本次查询，某个字段的值
     * @param string $field
     * @return array
     * @throws Throwable
     */
    public function getArrayByField(string $field): array
    {
        $ret = [];
        foreach ($this->field($field)->selectAll() as $item) {
            $val = $item->getByField($field);
            $ret[$val] = true; // 使用关联数组来去重
        }
        return array_keys($ret); // 转换为值数组;
    }

    /**
     * 获取一个格式化成 key => value 的数组
     * @param string $fieldId id字段
     * @param string $fieldName name字段
     * @return  integer[]
     * @throws Throwable
     */
    public function formatId2Name(string $fieldId, string $fieldName): array
    {
        $ret = [];
        foreach ($this->field([$fieldId, $fieldName])->selectAll() as $list) {
            $key = $list->getByField($fieldId);
            $value = $list->getByField($fieldName);
            $ret[$key] = $value;
        }
        return $ret;
    }

    /**
     * 获取一个格式化成 key => value 的数组
     * @param string $fieldId id字段
     * @return  static[]
     * @throws Throwable
     */
    public function formatId2Array(string $fieldId): array
    {
        $ret = [];
        foreach ($this->field($fieldId)->selectAll() as $list) {
            $key = $list->getByField($fieldId);
            $ret[$key] = $list;
        }
        return $ret;
    }


    /**
     * 获取查询缓存数据，适用于批量 ID 查询
     * @param integer[] $ids id 数组
     * @param string $idField 数据库ID字段的名称
     * @param integer $expire 缓存过期时间
     * @return static[]
     * @throws Throwable
     */
    public function getCacheByIds(array $ids, string $idField = self::PRI_KEY, int $expire = 3600): array
    {
        $tables = [];
        $remainingIds = [];
        PoolRedis::call(function (Redis $redis) use (&$remainingIds, &$tables, $ids, $idField) {
            // 遍历需要查询的ID，获取缓存数据
            // 如果有缓存就删除ID，并把缓存数据加入到结果中
            foreach ($ids as $cacheId) {
                $key = $this->_getCacheByIdsKey($idField, $cacheId);
                if ($data = $redis->hgetall($key)) {
                    $table = new static();
                    $table->__fromArray($data);
                    $tables[$cacheId] = $table;
                } else {
                    $remainingIds[] = $cacheId;
                }
            }
        });

        // 如果ID没有全部被删除 则继续查询
        if ($remainingIds) {

            // 如果设置了字段，就增加上ID 字段
            // 如果没有设置字段，构建SQL 的时候会增加所有字段
            if (!empty($this->_fieldArray)) {
                $this->field($idField);
            }

            // 这里查询全部字段，不然先查询一个字段少的，缓存了结果，再来查询其他字段就会获取不到
            foreach ($this->addWhere($idField, $remainingIds, 'in')->generator() as $table) {
                $cacheId = $table->getByField($idField);
                $tables[$cacheId] = $table;

                // 对查询结果进行缓存
                if ($expire) {
                    PoolRedis::call(function (Redis $redis) use ($table, $idField, $cacheId, $expire) {
                        $key = $this->_getCacheByIdsKey($idField, $cacheId);
                        $redis->hMSet($key, $table->__toArray());
                        $redis->expire($key, $expire);
                    });
                }
            }
        }

        return $tables;
    }

    public function setDebugSql(): static
    {
        $this->debugSql = true;
        return $this;
    }

    private function _getNumValue(string $field, string $type = 'int', int|float|null $def = null): int|float|null
    {
        if (!isset($this->_row[$field]) || !is_numeric($value = $this->_row[$field])) {
            return $def;
        }

        return $type === 'int' ? (int)$value : (float)$value;

    }

    private function _getArrayValue(string $field): array
    {
        $value = $this->_row[$field] ?? [];
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decodedValue = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decodedValue : explode(",", $value);
        }
        return $value;
    }


    private function _getCacheByIdsKey(string $idField, int $cacheId): string
    {
        return "getCacheByIds:" . static::TABLE_NAME . ":$idField:$cacheId";
    }

}