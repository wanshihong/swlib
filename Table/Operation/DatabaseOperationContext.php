<?php

namespace Swlib\Table\Operation;

use RuntimeException;

/**
 * 数据库操作事件基类
 * 包含数据库操作的详细信息
 */
class DatabaseOperationContext
{

    public function __construct(
        public string                 $database,       // 数据库名称
        public string                 $tableName,      // 表名
        public ?DatabaseOperationEnum $operation = null,      // 操作类型: insert, update, delete, select
        public string                 $sql = '',           // 执行的SQL语句
        public array                  $bindParams = [],     // 绑定的参数
        public array                  $affectedFields = [], // 影响的字段
        public array                  $writeData = [], // 本次写入的数据
        public mixed                  $result = null,       // 查询结果   select 有
        public float                  $startTime = 0.0, // 开始执行时间(毫秒)
        public float                  $executionTime = 0.0, // 执行时间(毫秒)
        public ?int                   $affectedRows = null,  // 影响的行数, update delete  insertAll 有
        public ?int                   $insertId = null,      // 插入的ID insert 有
        public array                  $whereConditions = [], // WHERE条件
        public bool                   $debugSql = false, //是否开启 SQL 调试日志（对应 setDebugSql）
        public ?string                $error = null // 执行异常信息（如果有）
    )
    {
    }


    /**
     * 获取详细信息
     */
    public function getDetails(): array
    {
        return [
            'operation' => $this->operation,
            'database' => $this->database,
            'table' => $this->tableName,
            'sql' => $this->sql,
            'bindParams' => $this->bindParams,
            'affectedFields' => $this->affectedFields,
            'writeData' => $this->writeData,
            'where_conditions' => $this->whereConditions,
            'result' => $this->result,
            'startTime' => $this->startTime,
            'executionTime' => $this->executionTime,
            'affected_rows' => $this->affectedRows,
            'insert_id' => $this->insertId,
            'debug_sql' => $this->debugSql,
            'error' => $this->error,
        ];
    }

    /**
     * 判断是否为写操作
     */
    public function isWriteOperation(): bool
    {
        return $this->operation->isWriteOperation();
    }

    /**
     * 判断是否为读操作
     */
    public function isReadOperation(): bool
    {
        return $this->operation->isReadOperation();
    }


    /**
     * 判断当前操作是否对指定字段进行了写入/修改。
     *
     * - insert/update：根据一维数组 writeData 的键判断
     * - insertAll    ：根据二维数组 writeData 中任意一行是否包含该字段判断
     *
     * @throws RuntimeException 在当前上下文没有写入数据（如 SELECT/DELETE/Db::query）时调用会抛异常
     */
    public function hasChangedField(string $field): bool
    {
        if (!$this->operation || !$this->operation->isWriteOperation() || empty($this->writeData)) {
            throw new RuntimeException('当前数据库操作没有写入数据，无法判断字段是否被修改');
        }

        $data = $this->writeData;

        // insertAll: 二维数组，每行是一条记录
        $first = reset($data);
        if (is_array($first)) {
            return array_any($data, fn($row) => is_array($row) && array_key_exists($field, $row));
        }

        // insert / update: 一维数组
        return array_key_exists($field, $data);
    }


    /**
     * 获取本次写操作中指定字段的新值。
     *
     * - insert/update：返回单个值（mixed）
     * - insertAll    ：返回数组 [行索引 => 值]
     *
     * @throws RuntimeException
     *   - 当前操作没有写入数据（如 SELECT/DELETE/Db::query）
     *   - 或该字段在本次写入中没有被设置
     */
    public function getChangedValue(string $field): mixed
    {
        if (!$this->operation || !$this->operation->isWriteOperation() || empty($this->writeData)) {
            throw new RuntimeException('当前数据库操作没有写入数据，无法获取字段的新值');
        }

        $data = $this->writeData;

        // insertAll: 二维数组，返回每一行对应字段的新值
        $first = reset($data);
        if (is_array($first)) {
            $values = [];
            foreach ($data as $index => $row) {
                if (array_key_exists($field, $row)) {
                    $values[$index] = $row[$field];
                }
            }

            if ($values === []) {
                throw new RuntimeException(sprintf('字段 %s 在本次写操作中没有被设置', $field));
            }

            return $values;
        }

        // insert / update: 一维数组
        if (!array_key_exists($field, $data)) {
            throw new RuntimeException(sprintf('字段 %s 在本次写操作中没有被设置', $field));
        }

        return $data[$field];
    }


    /**
     * 按字段常量，从 writeData 和 whereConditions 中尝试获取一个对应的值。
     *
     * 优先顺序：
     *   1. writeData 中的数据（insert/update 的新值；insertAll 取匹配到的第一行）
     *   2. whereConditions 中的 where 条件
     *
     * 如果都找不到，返回 null。
     */
    public function getValueByField(string $field): mixed
    {
        // 1. 先从 writeData 中找（insert / insertAll / update 的写入数据）
        if (!empty($this->writeData)) {
            $data  = $this->writeData;
            $first = reset($data);

            if (is_array($first)) {
                // insertAll: 二维数组，返回找到的第一行对应值
                foreach ($data as $row) {
                    if (is_array($row) && array_key_exists($field, $row)) {
                        return $row[$field];
                    }
                }
            } elseif (is_array($data) && array_key_exists($field, $data)) {
                // insert / update: 一维数组
                return $data[$field];
            }
        }

        // 2. 若 writeData 中未找到，再尝试从 whereConditions 中找
        return $this->findValueInWhere($this->whereConditions, $field);
    }


    /**
     * 在 where 条件数组结构中递归查找指定字段的值。
     *
     * 支持的格式包括：
     *   - [field => value]
     *   - [[field, operator, value], ...]
     *   - 嵌套 AND / OR 等复杂结构
     *
     * 说明：
     *   - 如果找不到，返回 null。
     */
    private function findValueInWhere(array $where, string $field): mixed
    {
        if ($where === []) {
            return null;
        }

        foreach ($where as $key => $value) {
            // 1) [field => value] 格式
            if (is_string($key) && $key === $field) {
                return $value;
            }

            // 2) [[field, operator, value], ...] 格式或嵌套数组
            if (is_array($value)) {
                // 简单 [field, op, val] 的情况
                if (array_key_exists(0, $value) && $value[0] === $field && array_key_exists(2, $value)) {
                    return $value[2];
                }

                // 对嵌套的 where 结构进一步查找
                $nested = $this->findValueInWhere($value, $field);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}