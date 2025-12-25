<?php

namespace Swlib\Utils;

use InvalidArgumentException;
use Throwable;

/**
 * 数据转换工具类
 *
 * 提供各种数据格式转换功能，包括字符串转数组、PHP 值导出等
 */
class DataConverter
{
    /**
     * 尝试把字符串转换成数组
     *
     * 会自行判断是 JSON 格式或者逗号分割格式
     *
     * 转换顺序：
     * 1. 如果已经是数组，直接返回
     * 2. 如果是 null 或空字符串，返回空数组
     * 3. 如果是数字，返回包含该数字的数组
     * 4. 尝试解析为 JSON
     * 5. 尝试按逗号分割
     * 6. 将原始值作为数组的唯一元素
     *
     * 示例：
     * ```php
     * $arr = DataConverter::convertToArray([1, 2, 3]);  // 返回：[1, 2, 3]
     * $arr = DataConverter::convertToArray('1,2,3');    // 返回：['1', '2', '3']
     * $arr = DataConverter::convertToArray('[1,2,3]');  // 返回：[1, 2, 3]
     * $arr = DataConverter::convertToArray('123');      // 返回：['123']
     * $arr = DataConverter::convertToArray(null);       // 返回：[]
     * ```
     *
     * @param mixed $value 需要转换的值
     * @return array 转换后的数组
     */
    public static function convertToArray(mixed $value): array
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
     * 将 PHP 值导出为短语法字符串（[] 风格）
     *
     * 支持的类型：
     * - null: 导出为 'null'
     * - bool: 导出为 'true' 或 'false'
     * - int/float/string: 使用 var_export
     * - array: 导出为 [...] 格式，支持递归
     *
     * 示例：
     * ```php
     * $export = DataConverter::exportShort(null);           // 返回：'null'
     * $export = DataConverter::exportShort(true);           // 返回：'true'
     * $export = DataConverter::exportShort([1, 2, 3]);      // 返回：'[1, 2, 3,]'
     * $export = DataConverter::exportShort(['a' => 1]);     // 返回：'['a' => 1,]'
     * ```
     *
     * @param mixed $var 需要导出的 PHP 值
     * @param int $indent 当前缩进层级（内部用）
     * @return string 导出后的字符串
     * @throws InvalidArgumentException 当遇到不支持的类型时
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
                return var_export($var, true);       // 数字直接复用，自带转义
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
                        ? self::exportShort($v, $indent + 1)
                        : var_export($k, true) . ' => ' . self::exportShort($v, $indent + 1);
                    $body .= $nextPad . $part . ",\n";
                }
                return "[\n" . $body . $pad . ']';
            default:
                throw new InvalidArgumentException('Unsupported type: ' . gettype($var));
        }
    }
}

