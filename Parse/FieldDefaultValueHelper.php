<?php
declare(strict_types=1);

namespace Swlib\Parse;

/**
 * 字段默认值处理辅助类
 * 统一处理 ParseTableTableDto 和 ParseTableTable 中的字段默认值逻辑
 */
class FieldDefaultValueHelper
{
    /**
     * 获取字段的默认值配置
     *
     * @param string $dbFieldType 数据库字段类型
     * @param mixed $dbDefault 数据库默认值
     * @param bool $allowNull 是否允许为空
     * @return array 返回包含默认值信息的数组
     */
    public static function getFieldDefaultConfig(string $dbFieldType, mixed $dbDefault, bool $allowNull): array
    {
        // 处理字符串默认值
        $strDefaultStr = $dbDefault ? '"' . $dbDefault . '"' : '""';

        // 处理 CURRENT_TIMESTAMP 特殊情况
        $dbCurrentTimestampDefault = $dbDefault;
        if ($dbDefault === 'CURRENT_TIMESTAMP') {
            $dbCurrentTimestampDefault = 'date(\'Y-m-d H:i:s\')';
        }

        $conf = [
            [
                'types' => ['tinyint', 'smallint', 'int', 'bigint'],
                'php_default' => $dbDefault ? intval($dbDefault) : 0,
                'sql_default' => self::formatSqlDefault($dbDefault, 'int'),
                'type' => 'int'
            ],
            [
                'types' => ['float', 'decimal'],
                'php_default' => $dbDefault ? floatval($dbDefault) : 0,
                'sql_default' => self::formatSqlDefault($dbDefault, 'float'),
                'type' => 'float'
            ],
            [
                'types' => ['timestamp'],
                'php_default' => '""',
                'sql_default' => self::formatSqlDefault($dbDefault, 'timestamp'),
                'type' => 'string',
                'special_default' => $dbCurrentTimestampDefault
            ],
            [
                'types' => ['json'],
                'php_default' => $strDefaultStr,
                'sql_default' => self::formatSqlDefault($dbDefault, 'json'),
                'type' => 'string'
            ],
        ];

        // 默认配置（字符串类型）
        $defaultConfig = [
            'php_default' => $strDefaultStr,
            'sql_default' => self::formatSqlDefault($dbDefault, 'string'),
            'type' => 'string'
        ];

        // 查找匹配的配置
        foreach ($conf as $item) {
            foreach ($item['types'] as $confType) {
                if (str_starts_with($dbFieldType, $confType)) {
                    $config = $item;
                    break 2;
                }
            }
        }

        // 如果没有找到匹配的配置，使用默认配置
        if (!isset($config)) {
            $config = $defaultConfig;
        }

        // 处理允许为空的情况
        if ($allowNull) {
            $config['type'] = '?' . $config['type'];
        }

        return $config;
    }

    /**
     * 格式化 SQL 默认值
     *
     * @param mixed $dbDefault 数据库默认值
     * @param string $fieldType 字段类型
     * @return string 格式化后的 SQL 默认值
     */
    private static function formatSqlDefault(mixed $dbDefault, string $fieldType): string
    {
        if ($dbDefault === null) {
            return 'null';
        }

        if ($dbDefault === '') {
            return "''";
        }

        // 对于特殊的时间戳默认值
        if ($dbDefault === 'CURRENT_TIMESTAMP') {
            return "'CURRENT_TIMESTAMP'";
        }

        // 对于数值类型，直接返回
        if (in_array($fieldType, ['int', 'float']) && is_numeric($dbDefault)) {
            return (string)$dbDefault;
        }

        // 对于字符串类型，添加引号
        if (is_string($dbDefault)) {
            return "'" . $dbDefault . "'";
        }

        return (string)$dbDefault;
    }

    /**
     * 获取 DTO 字段的 getter/setter 配置
     *
     * @param string $field 字段名
     * @param array $config 字段配置
     * @param bool $allowNull 是否允许为空
     * @return array 返回 getter/setter 配置
     */
    public static function getDtoFieldAccessors(string $field, array $config, bool $allowNull): array
    {
        $getStr = "";
        $setValueStr = ""; // set hook 中赋值的表达式

        // 根据字段类型生成不同的 getter/setter
        $baseType = str_replace('?', '', $config['type']);

        switch ($baseType) {
            case 'float':
            case 'int':
                $getStr = "get => \$this->$field===null ? 0 : \$this->$field;";
                $setValueStr = "\$value===null ? 0 : \$value";
                break;
            case 'string':
                if (isset($config['special_default'])) {
                    // 处理 timestamp 等特殊情况
                    $specialDefault = $config['special_default'];
                    $getStr = "get => empty(\$this->$field) ? $specialDefault : \$this->$field;";
                    $setValueStr = "empty(\$value) ? $specialDefault : \$value";
                    $allowNull = true;
                } else {
                    $getStr = "get => \$this->$field===null ? \"\" : \$this->$field;";
                    $setValueStr = "\$value===null ? \"\" : \$value";
                }
                break;
        }

        // 如果不允许为空，则不需要 getter
        if ($allowNull === false) {
            $getStr = "";
        }

        return [
            'get' => $getStr,
            'set_value' => $setValueStr  // 返回赋值表达式，而不是完整的 set 语句
        ];
    }
}