<?php

declare(strict_types=1);

namespace Swlib\Parse;

use Generate\DatabaseConnect;
use ReflectionClass;
use Swlib\Attribute\I18nAttribute;
use Throwable;

/**
 * I18n 解析器
 * 将 AppErr 类中的常量注解解析并写入 language 表
 */
class ParseI18n
{

    /**
     * 同步所有 i18n key 到 language 表
     *
     * @return array ['inserted' => int, 'updated' => int, 'skipped' => int]
     * @throws Throwable
     */
    public function __construct()
    {
        // 定义需要解析的 AppErr 类
        $classes = [
            \Swlib\Exception\AppErr::class,
            \App\Exception\AppErr::class,
        ];

        $stats = [
            'inserted' => 0, // 新增数量
            'updated' => 0,  // 更新数量
            'skipped' => 0,  // 跳过数量（解析失败）
        ];


        // 解析每个类并写入数据库
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                echo "类不存在，跳过: $class" . PHP_EOL;
                continue;
            }

            $translations = self::parseAppErrClass($class);
            echo "从 $class 解析到 " . count($translations) . " 个常量" . PHP_EOL;

            foreach ($translations as $key => $langData) {
                self::writeToLanguageTable($key, $langData, $stats);
            }
        }

        echo "翻译同步完成:" . PHP_EOL;
        echo "- 新增: {$stats['inserted']}" . PHP_EOL;
        echo "- 更新: {$stats['updated']}" . PHP_EOL;
        echo "- 跳过: {$stats['skipped']}" . PHP_EOL;

        return $stats;
    }

    /**
     * 解析 AppErr 类获取所有常量的翻译
     *
     * @param class-string $className 类名
     * @return array ['key' => ['zh' => '中文', 'en' => 'English', ...]]
     */
    private static function parseAppErrClass(string $className): array
    {
        $translations = [];

        try {
            $reflection = new ReflectionClass($className);
            $constants = $reflection->getReflectionConstants();

            foreach ($constants as $constant) {
                // 获取常量的 I18nAttribute 注解
                $attributes = $constant->getAttributes(I18nAttribute::class);

                if (empty($attributes)) {
                    // 没有注解，跳过
                    continue;
                }

                /** @var I18nAttribute $i18nAttr */
                $i18nAttr = $attributes[0]->newInstance();
                $key = $constant->getValue();

                // 获取所有语言的翻译
                $translations[$key] = $i18nAttr->getTranslations();
            }
        } catch (Throwable $e) {
            echo "解析类 $className 失败: " . $e->getMessage() . PHP_EOL;
        }

        return $translations;
    }

    /**
     * 写入或更新 language 表（支持多语言）
     *
     * @param string $key i18n key
     * @param array $langData ['lang_code' => 'translation', ...]
     * @param array &$stats 统计数据
     * @throws Throwable
     */
    private static function writeToLanguageTable(string $key, array $langData, array &$stats): void
    {
        // 转义 SQL 特殊字符
        $keyEscaped = addslashes($key);

        // 构建字段更新部分
        $updateFields = [];
        $insertFields = ['`key`'];
        $insertValues = ["'$keyEscaped'"];

        foreach ($langData as $lang => $value) {
            $valueEscaped = addslashes($value);
            $updateFields[] = "`$lang` = '$valueEscaped'";
            $insertFields[] = "`$lang`";
            $insertValues[] = "'$valueEscaped'";
        }

        $time = time();
        $updateFields[] = "`use_time` = $time";
        $insertFields[] = "`use_time`";
        $insertValues[] = "$time";

        // 先检查 key 是否存在
        $existing = DatabaseConnect::query("SELECT `id` FROM `language` WHERE `key` = '$keyEscaped' LIMIT 1")->fetch_assoc();

        try {
            if (empty($existing)) {
                // 不存在，插入新记录
                $sql = "INSERT INTO `language` (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $result = DatabaseConnect::query($sql);

                if ($result) {
                    $stats['inserted']++;
                    echo "  [新增] $key: {$langData['zh']}" . PHP_EOL;
                } else {
                    echo "  [失败] $key: 插入失败" . PHP_EOL;
                    $stats['skipped']++;
                }
            } else {
                // 已存在，更新所有语言字段和 use_time
                $id = (int)$existing['id'];
                $sql = "UPDATE `language` SET " . implode(', ', $updateFields) . " WHERE `id` = $id";
                $result = DatabaseConnect::query($sql);

                if ($result) {
                    $stats['updated']++;
//                    echo "  [更新] $key: {$langData['zh']}" . PHP_EOL;
                } else {
                    echo "  [失败] $key: 更新失败" . PHP_EOL;
                    $stats['skipped']++;
                }
            }
        } catch (Throwable $e) {
            echo "  [失败] $key: " . $e->getMessage() . PHP_EOL;
            $stats['skipped']++;
        }
    }

}
