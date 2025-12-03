<?php

namespace Swlib\Table\Event;

/**
 * 数据库操作事件基类
 * 包含数据库操作的详细信息
 */
class DatabaseOperationEvent
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
}