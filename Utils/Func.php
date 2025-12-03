<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use InvalidArgumentException;
use Swlib\Enum\CtxEnum;
use Throwable;

class Func
{

    /**
     * 获取当前请求的域名
     * @return string
     */
    public static function getHost(): string
    {
        $request = CtxEnum::Request->get();
        $host = $request->header['x-forwarded-host'] ?? $request->header['host'] ?? 'unknown';

        if (str_starts_with($host, 'http')) {
            return $host;
        }
        $scheme = self::isHttps() ? 'https' : 'http';
        return "$scheme://$host";
    }


    /**
     * 判断当前请求是否 https
     */
    public static function isHttps(): bool
    {
        if (ConfigEnum::HTTPS) {
            return true;
        }

        $request = CtxEnum::Request->get();
        // 检查 x-forwarded-proto 头部
        if (isset($request->header['x-forwarded-proto']) && $request->header['x-forwarded-proto'] === 'https') {
            return true;
        }

        // 检查 scheme 头部
        if (isset($request->header['scheme']) && $request->header['scheme'] === 'https') {
            return true;
        }


        // 默认返回 false
        return false;
    }

    public static function getCookieKey(string $key): string
    {
        return $key . ":" . ConfigEnum::PROJECT_UNIQUE;
    }


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
     * $camelCase = Func::underscoreToCamelCase('user_id', '_', false);     // 返回：userId
     * $camelCase = Func::underscoreToCamelCase('created_at', '_', false);  // 返回：createdAt
     * $camelCase = Func::underscoreToCamelCase('product_name', '_', false); // 返回：productName
     * $camelCase = Func::underscoreToCamelCase('id', '_', false);          // 返回：id
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
     * $camelCaseData = Func::convertArrayKeysToCamelCase($dbData);
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

    public static function getPrefixBeforeUnderscore($string)
    {
        return strstr($string, '_', true) ?: $string;
    }


    /**
     * 尝试把 字符串转换成 array
     * 会自行判断 是 json 或者 逗号分割
     * @param $value
     * @return array
     */
    public static function convertToArray($value): array
    {
        $valueArr = [];

        // 如果已经是数组，直接返回
        if (is_array($value)) {
            return $value;
        }

        // 如果是null或空字符串，返回空数组
        if ($value === null || $value === '') {
            return [];
        }

        try {
            // 处理数字值
            if (is_numeric($value)) {
                $valueArr[] = $value;
                return $valueArr;
            }

            // 尝试解析JSON
            $jsonData = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($jsonData) ? $jsonData : [$jsonData];
            }

            // 尝试按逗号分割
            $exploded = explode(',', $value);
            if (count($exploded) > 1) {
                // 去除每个元素两端的空白字符
                $exploded = array_map('trim', $exploded);
                // 过滤掉空元素
                $exploded = array_filter($exploded, function ($item) {
                    return $item !== '';
                });
                return array_values($exploded); // 重新索引数组
            }

            // 如果都不是，将原始值作为数组的唯一元素
            return [$value];

        } catch (Throwable) {
            // 如果发生异常，尝试按逗号分割作为最后手段
            $exploded = explode(',', $value);
            return array_map('trim', $exploded);
        }
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
     * $underscore = Func::camelCaseToUnderscore('userId');        // 返回：user_id
     * $underscore = Func::camelCaseToUnderscore('createdAt');     // 返回：created_at
     * $underscore = Func::camelCaseToUnderscore('productName');   // 返回：product_name
     * $underscore = Func::camelCaseToUnderscore('id');            // 返回：id
     * $underscore = Func::camelCaseToUnderscore('XMLHttpRequest'); // 返回：xml_http_request
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
     * 将 PHP 值导出为短语法字符串（[] 风格）
     * @param mixed $var
     * @param int $indent 当前缩进层级（内部用）
     * @return string
     */
    public static function exportShort(mixed $var, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);          // 4 空格缩进
        $nextPad = str_repeat('    ', $indent + 1);

        switch (true) {
            case is_null($var):
                return 'null';
            case is_bool($var):
                return $var ? 'true' : 'false';
            case is_int($var):
            case is_string($var):
            case is_float($var):
                return var_export($var, true);       // 数字直接复用
            // 自带转义
            case is_array($var):
                // 空数组
                if (empty($var)) {
                    return '[]';
                }
                // 检测是否纯索引、连续 0..n
                $keys = array_keys($var);
                $isList = ($keys === range(0, count($var) - 1));

                $body = '';
                foreach ($var as $k => $v) {
                    $part = $isList
                        ? Func::exportShort($v, $indent + 1)
                        : var_export($k, true) . ' => ' . Func::exportShort($v, $indent + 1);
                    $body .= $nextPad . $part . ",\n";
                }
                return "[\n" . $body . $pad . ']';
            default:
                throw new InvalidArgumentException('Unsupported type: ' . gettype($var));
        }
    }


}