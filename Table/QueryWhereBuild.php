<?php

namespace Swlib\Table;


use Swlib\Table\Interface\TableInterface;

/**
 * 高级 WHERE 条件构建器
 * 支持无限层嵌套的复杂查询条件
 */
class QueryWhereBuild
{
    private array $bindParams = [];
    private ?TableInterface $table = null;

    /**
     * 设置表实例用于字段格式化
     */
    public function setTable(TableInterface $table): void
    {
        $this->table = $table;
    }

    /**
     * 构建复杂的 WHERE 条件
     *
     * @param array $conditions 条件数组
     * @return array ['sql' => string, 'params' => array]
     */
    public function buildWhere(array $conditions): array
    {
        $this->bindParams = [];
        $sql = $this->parseConditions($conditions);

        return [
            'sql' => $sql,
            'params' => $this->bindParams
        ];
    }

    /**
     * 递归解析条件数组 - 支持无限层嵌套
     *
     * @param array $conditions 条件数组
     * @param string $defaultLogic 默认逻辑连接符
     * @return string
     */
    private function parseConditions(array $conditions, string $defaultLogic = 'AND'): string
    {
        if (empty($conditions)) {
            return '';
        }

        // 统一处理关联数组格式的条件 (field => value) 把关联数组标准化为 [field, operator, value] 格式
        $conditions = $this->normalizeConditions($conditions);

        $parts = [];
        $i = 0;

        while ($i < count($conditions)) {
            $condition = $conditions[$i];

            // 处理逻辑连接符
            if (is_string($condition) && in_array(strtoupper($condition), ['AND', 'OR'])) {
                if (!empty($parts)) {
                    $parts[] = strtoupper($condition);
                }
                $i++;
                continue;
            }

            // 处理数组条件
            if (is_array($condition)) {
                // 如果是查询条件 [field, operator, value]
                if ($this->isQueryCondition($condition)) {
                    $conditionSql = $this->buildSingleCondition($condition);
                    if (!empty($conditionSql)) {
                        $parts[] = $conditionSql;
                    }
                } // 如果是嵌套数组（子条件组）- 递归处理
                else {
                    $subSql = $this->parseConditions($condition, $defaultLogic);
                    if (!empty($subSql)) {
                        $parts[] = "($subSql)";
                    }
                }
            }

            $i++;
        }

        // 使用默认逻辑连接符连接没有明确指定连接符的条件
        return $this->joinParts($parts, $defaultLogic);
    }

    /**
     * 连接条件部分
     */
    private function joinParts(array $parts, string $defaultLogic): string
    {
        if (empty($parts)) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $result = '';
        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if (in_array($part, ['AND', 'OR'])) {
                $result .= " $part ";
            } else {
                if ($i > 0 && !preg_match('/\s+(AND|OR)\s*$/', $result)) {
                    $result .= " $defaultLogic ";
                }
                $result .= $part;
            }
        }

        return $result;
    }

    /**
     * 判断是否为查询条件
     */
    private function isQueryCondition(array $condition): bool
    {
        return count($condition) >= 2 && count($condition) <= 4 &&
            is_string($condition[0]) &&
            !in_array(strtoupper($condition[0]), ['AND', 'OR']);
    }

    /**
     * 构建单个查询条件
     */
    private function buildSingleCondition(array $condition): string
    {
        $field = $condition[0];
        // 标准化 operator：去除两边空格，将中间连续空格标准化为单个空格
        $operator = strtolower(preg_replace('/\s+/', ' ', trim($condition[1])));
        $value = $condition[2] ?? null;

        // 格式化字段名
        $field = $this->formatField($field);

        switch ($operator) {
            case 'is null':
            case 'is not null':
                return "$field $operator";

            case 'in':
            case 'not in':
                if (!is_array($value) || empty($value)) {
                    return '';
                }
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $this->bindParams = array_merge($this->bindParams, $value);
                return "$field $operator ($placeholders)";

            case 'between':
                if (!is_array($value) || count($value) !== 2) {
                    return '';
                }
                $this->bindParams[] = $value[0];
                $this->bindParams[] = $value[1];
                return "$field $operator ? AND ?";

            case 'json_contains':
                /**
                 * JSON_CONTAINS 查询用法说明
                 *
                 * 语法：JSON_CONTAINS(target, candidate[, path])
                 *
                 * 参数说明：
                 * - target: 目标 JSON 文档（字段）
                 * - candidate: 要搜索的候选值（必须是有效的 JSON 格式）
                 * - path: 可选，指定搜索路径，默认为 '$'（根路径）
                 *
                 * 路径参数示例：
                 * - '$' : 根路径（默认）
                 * - '$.key' : 指定键的路径
                 * - '$.array[0]' : 数组索引路径
                 * - '$.key.subkey' : 嵌套对象路径
                 * - '$.array[*]' : 数组中的所有元素
                 *
                 * 使用示例：
                 * 1. 基础用法（不指定路径）：
                 *    ['tags', 'json_contains', 'important']
                 *    生成：JSON_CONTAINS(`tags`, '"important"', '$')
                 *
                 * 2. 指定路径：
                 *    ['user_info', 'json_contains', 'admin', '$.role']
                 *    生成：JSON_CONTAINS(`user_info`, '"admin"', '$.role')
                 *
                 * 3. 数组值搜索：
                 *    ['permissions', 'json_contains', ['read', 'write']]
                 *    生成：(JSON_CONTAINS(`permissions`, '"read"', '$') OR JSON_CONTAINS(`permissions`, '"write"', '$'))
                 *
                 * 4. 复杂对象搜索：
                 *    ['config', 'json_contains', ['enabled' => true], '$.features']
                 *    生成：JSON_CONTAINS(`config`, '{"enabled":true}', '$.features')
                 *
                 * 注意事项：
                 * - candidate 参数会自动使用 json_encode() 转换为有效的 JSON 格式
                 * - 字符串值会被自动加上双引号，如 "important" -> '"important"'
                 * - 数组和对象会被转换为相应的 JSON 格式
                 * - 如果提供多个值，会生成多个 OR 条件
                 */
                if ($this->isQueryEmptyValue($value)) {
                    return '';
                }
                $node = $condition[3] ?? '$';
                if (!is_array($value)) {
                    $value = [$value];
                }
                $jsonParts = [];
                foreach ($value as $v) {
                    $jsonParts[] = "JSON_CONTAINS($field, ?, ?)";
                    // 确保正确的 JSON 编码
                    // 如果是字符串，使用 json_encode 确保生成有效的 JSON 格式
                    // 如果是数组或对象，也使用 json_encode 转换
                    if (is_string($v)) {
                        // 字符串值会被 json_encode 自动加上双引号
                        $jsonValue = json_encode($v, JSON_UNESCAPED_UNICODE);
                    } elseif (is_array($v) || is_object($v)) {
                        // 数组和对象转换为 JSON 格式
                        $jsonValue = json_encode($v, JSON_UNESCAPED_UNICODE);
                    } elseif (is_bool($v)) {
                        // 布尔值转换为 JSON 格式
                        $jsonValue = json_encode($v);
                    } elseif (is_null($v)) {
                        // null 转换为 JSON 格式
                        $jsonValue = 'null';
                    } else {
                        // 数字等其他类型直接转换
                        $jsonValue = json_encode($v);
                    }
                    $this->bindParams[] = $jsonValue;
                    $this->bindParams[] = $node;
                }
                return '(' . implode(' OR ', $jsonParts) . ')';

            default:
                if ($this->isQueryEmptyValue($value)) {
                    return '';
                }

                if (!is_array($value)) {
                    $value = [$value];
                }

                $conditionParts = [];
                foreach ($value as $v) {
                    if ($operator === 'like') {
                        $v = trim($v, "\"'");
                    }
                    $conditionParts[] = "$field $operator ?";
                    $this->bindParams[] = $v;
                }

                if (count($conditionParts) === 1) {
                    return $conditionParts[0];
                } else {
                    return '(' . implode(' OR ', $conditionParts) . ')';
                }
        }
    }

    /**
     * 判断是否需要参与查询，  数字 0  字符串 “” 都是需要参与查询的
     * @param mixed $value
     * @return bool
     */
    private function isQueryEmptyValue(mixed $value): bool
    {
        // 数字 0 / 字符串 '0' 明确为非空
        if (is_numeric($value)) {
            return false;
        }

        // 只认为 null、'' 是空值，其余字符串都非空
        if ($value === '') {
            return false;
        }

        // 其余类型（bool、object、resource...）一律用 empty
        return empty($value);
    }

    /**
     * 统一处理条件数组格式，将关联数组转换为标准格式
     *
     * @param array $conditions 原始条件数组
     * @return array 标准化后的条件数组
     */
    private function normalizeConditions(array $conditions): array
    {
        $normalized = [];

        foreach ($conditions as $key => $value) {
            if (is_string($key)) {
                // 关联数组格式，转换为标准格式
                $normalized[] = [$key, '=', $value];
            } elseif (is_array($value) && count($value) === 3) {
                // 已经是标准格式，保持不变
                $normalized[] = $value;
            } else {
                // 逻辑运算符或其他值，保持不变
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * 格式化字段名
     * 如果设置了表实例，则使用表的字段格式化逻辑
     * 否则直接添加反引号
     */
    private function formatField(string $field): string
    {
        if ($field === '*') {
            return '*';
        }

        if ($this->table) {
            return $this->table->formatField($field);
        }


        // 默认只添加反引号
        return "`$field`";
    }
}