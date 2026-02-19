<?php
declare(strict_types=1);

namespace Swlib\Table\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class FieldMetadata
{
    public function __construct(
        // ===== 基础信息 =====
        public string $field,             // 字段常量值 (如: t12f8)
        public string $name,              // 数据库原始字段名 (如: id, user_name)
        public string $alias,             // DTO 属性名 (如: id, userName)
        public string $description,       // 字段描述/注释
        public string $dbName,            // 数据库名 (如: task_web)
        public string $tableName,         // 表名 (如: user)
        public string $type,              // 字段类型 (如: bigint, varchar, int)

        // ===== 验证规则 =====
        public bool $nullable = false,         // 是否可空
        public mixed $default = null,          // 默认值
        public ?int $length = null,            // 长度 (varchar 长度等)
        public ?array $enumValues = null,      // 枚举可选值

        // ===== 扩展属性 =====
        public bool $isPrimary = false,        // 是否主键
        public bool $isUnique = false,         // 是否唯一索引
        public bool $isIndex = false,          // 是否普通索引
        public bool $isSensitive = false,      // 是否敏感字段（脱敏显示）
        public bool $isHidden = false,         // 是否在 API 响应中隐藏
        public bool $isReadOnly = false,       // 是否只读（不可写入）
        public bool $isAutoIncrement = false,  // 是否自增
        public ?string $phpType = null,        // PHP 类型（int, string, array）
        public ?string $protoType = null,      // Proto 类型（int32, string, message）
    ) {
    }
}
