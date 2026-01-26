<?php

declare(strict_types=1);

namespace Swlib\Parse;

use Generate\DatabaseConnect;
use Throwable;

/**
 * I18n 解析器
 * 将 AppErr 类中的常量解析并写入 language 表
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
        // 定义需要解析的 AppErr 文件路径
        $files = [
            SWLIB_DIR . '/Exception/AppErr.php',
            ROOT_DIR . 'backed/App/Exception/AppErr.php',
        ];

        $stats = [
            'inserted' => 0, // 新增数量
            'updated' => 0,  // 更新数量
            'skipped' => 0,  // 跳过数量（解析失败）
        ];


        // 解析每个文件并写入数据库
        foreach ($files as $file) {
            if (!file_exists($file)) {
                echo "文件不存在，跳过: $file" . PHP_EOL;
                continue;
            }

            $constants = self::parseAppErrFile($file);
            echo "从 $file 解析到 " . count($constants) . " 个常量" . PHP_EOL;

            foreach ($constants as $key => $zh) {
                self::writeToLanguageTable($key, $zh, $stats);
            }
        }

        echo PHP_EOL . "同步完成:" . PHP_EOL;
        echo "- 新增: {$stats['inserted']}" . PHP_EOL;
        echo "- 更新: {$stats['updated']}" . PHP_EOL;
        echo "- 跳过: {$stats['skipped']}" . PHP_EOL;

        return $stats;
    }

    /**
     * 解析 AppErr 文件获取所有常量
     *
     * @param string $filePath 文件路径
     * @return array ['key' => '中文注释']
     */
    private static function parseAppErrFile(string $filePath): array
    {
        $constants = [];
        $content = file_get_contents($filePath);

        // 匹配 public const string XXXX = 'key'; // 注释
        // 支持多行注释
        if (!preg_match_all('/public\s+const\s+string\s+(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]\s*;\s*\/\/\s*(.+)$/m', $content, $matches)) {
            return [];
        }

        foreach ($matches[1] as $index => $_) {
            $key = $matches[2][$index];
            $zh = $matches[3][$index] ?? '';

            // 去掉注释末尾的句号、逗号等标点
            $zh = rtrim($zh, '，。；,;');

            if (!empty($zh)) {
                $constants[$key] = $zh;
            }
        }

        return $constants;
    }

    /**
     * 写入或更新 language 表
     *
     * @param string $key i18n key
     * @param string $zh 中文注释
     * @param array &$stats 统计数据
     * @throws Throwable
     */
    private static function writeToLanguageTable(string $key, string $zh, array &$stats): void
    {
        // 转义 SQL 特殊字符
        $keyEscaped = addslashes($key);
        $zhEscaped = addslashes($zh);

        // 先检查 key 是否存在
        $existing = DatabaseConnect::query("SELECT id FROM `language` WHERE `key` = '$keyEscaped' LIMIT 1")->fetch_assoc();

        $time = time();

        if (empty($existing)) {
            // 不存在，插入新记录
            $sql = "INSERT INTO `language` (`key`, `zh`, `use_time`) VALUES ('$keyEscaped', '$zhEscaped', $time)";
            $result = DatabaseConnect::query($sql);

            if ($result) {
                $stats['inserted']++;
                echo "  [新增] $key: $zh" . PHP_EOL;
            } else {
                echo "  [失败] $key: 插入失败" . PHP_EOL;
                $stats['skipped']++;
            }
        } else {
            // 已存在，更新 zh 字段和 use_time
            $id = (int)$existing['id'];
            $sql = "UPDATE `language` SET `zh` = '$zhEscaped', `use_time` = $time WHERE id = $id";
            $result = DatabaseConnect::query($sql);

            if ($result) {
                $stats['updated']++;
                echo "  [更新] $key: $zh" . PHP_EOL;
            } else {
                echo "  [失败] $key: 更新失败" . PHP_EOL;
                $stats['skipped']++;
            }
        }
    }

}
