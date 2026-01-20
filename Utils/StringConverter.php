<?php

namespace Swlib\Utils;


/**
 * 字符串转换工具类
 * 
 * 提供驼峰命名和下划线命名之间的转换功能
 * 主要用于数据库字段名和对象属性名的转换
 */
class StringConverter
{
    /**
     * 将下划线分隔的字符串转换为小驼峰命名（首字母小写）
     *
     * 使用场景：
     * - 数据库字段名转换为对象属性名（如：user_id -> userId）
     * - API 数据格式转换
     * - 配置项名称转换
     *
     * 示例：
     * ```php
     * $camelCase = StringConverter::underscoreToCamelCase('user_id', '_', false);     // 返回：userId
     * $camelCase = StringConverter::underscoreToCamelCase('created_at', '_', false);  // 返回：createdAt
     * $camelCase = StringConverter::underscoreToCamelCase('product_name', '_', false); // 返回：productName
     * $camelCase = StringConverter::underscoreToCamelCase('id', '_', false);          // 返回：id
     * ```
     *
     * @param string $string 需要转换的字符串（下划线格式）
     * @param string $separator 分隔符，默认为下划线
     * @param bool $upperFirst 是否首字母大写，默认为true（大驼峰），false为小驼峰
     * @return string 转换后的驼峰命名字符串
     */
    public static function underscoreToCamelCase(string $string, string $separator = "_", bool $upperFirst = true): string
    {
        // 如果字符串中没有分隔符，根据 upperFirst 参数决定是否首字母大写
        if (!str_contains($string, $separator)) {
            return $upperFirst ? ucfirst($string) : $string;
        }

        // 将分隔符分隔的字符串转换为驼峰命名
        $words = explode($separator, $string);
        $result = array_map('ucfirst', $words);
        $camelCase = implode('', $result);

        // 如果需要小驼峰，将首字母小写
        return $upperFirst ? $camelCase : lcfirst($camelCase);
    }

    /**
     * 将驼峰命名转换为下划线分隔的小写字符串
     *
     * 使用场景：
     * - 对象属性名转换为数据库字段名（如：userId -> user_id）
     * - API 数据格式转换
     * - 配置项名称转换
     *
     * 示例：
     * ```php
     * $underscore = StringConverter::camelCaseToUnderscore('userId');        // 返回：user_id
     * $underscore = StringConverter::camelCaseToUnderscore('createdAt');     // 返回：created_at
     * $underscore = StringConverter::camelCaseToUnderscore('productName');   // 返回：product_name
     * $underscore = StringConverter::camelCaseToUnderscore('id');            // 返回：id
     * $underscore = StringConverter::camelCaseToUnderscore('XMLHttpRequest'); // 返回：xml_http_request
     * ```
     *
     * @param string $string 需要转换的驼峰命名字符串
     * @param string $separator 分隔符，默认为下划线
     * @return string 转换后的下划线分隔小写字符串
     */
    public static function camelCaseToUnderscore(string $string, string $separator = "_"): string
    {
        // 如果字符串为空，直接返回
        if (empty($string)) {
            return $string;
        }

        // 使用正则表达式在大写字母前插入分隔符
        // 处理连续大写字母的情况，如 XMLHttpRequest -> XML_Http_Request
        $result = preg_replace('/([a-z])([A-Z])/', '$1' . $separator . '$2', $string);
        $result = preg_replace('/([A-Z])([A-Z][a-z])/', '$1' . $separator . '$2', $result);

        // 转换为小写
        return strtolower($result);
    }

    /**
     * 批量转换数组中的键名：从下划线格式转换为小驼峰格式
     *
     * 使用场景：
     * - 原生 SQL 查询返回的关联数组，需要将所有键名转换为小驼峰格式
     * - API 数据格式转换
     *
     * 示例：
     * ```php
     * $dbData = [
     *     'user_id' => 1,
     *     'user_name' => 'John',
     *     'created_at' => '2023-01-01',
     *     'is_active' => true
     * ];
     *
     * $camelCaseData = StringConverter::convertArrayKeysToCamelCase($dbData);
     * // 结果：
     * // [
     * //     'userId' => 1,
     * //     'userName' => 'John',
     * //     'createdAt' => '2023-01-01',
     * //     'isActive' => true
     * // ]
     * ```
     *
     * @param array $array 需要转换键名的数组
     * @param bool $recursive 是否递归转换多维数组，默认为 false
     * @return array 转换后的数组
     */
    public static function convertArrayKeysToCamelCase(array $array, bool $recursive = false): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            // 转换键名为小驼峰格式
            $camelKey = is_string($key) ? self::underscoreToCamelCase($key, '_', false) : $key;

            // 如果需要递归处理且值是数组
            if ($recursive && is_array($value)) {
                $value = self::convertArrayKeysToCamelCase($value, true);
            }

            $result[$camelKey] = $value;
        }

        return $result;
    }

    /**
     * 获取字符串中下划线之前的前缀部分
     *
     * 示例：
     * ```php
     * $prefix = StringConverter::getPrefixBeforeUnderscore('user_id');  // 返回：user
     * $prefix = StringConverter::getPrefixBeforeUnderscore('no_underscore');  // 返回：no
     * $prefix = StringConverter::getPrefixBeforeUnderscore('simple');  // 返回：simple
     * ```
     *
     * @param string $string 输入字符串
     * @return string 下划线之前的前缀，如果没有下划线则返回原字符串
     */
    public static function getPrefixBeforeUnderscore(string $string): string
    {
        return strstr($string, '_', true) ?: $string;
    }



}

