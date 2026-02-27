<?php

declare(strict_types=1);

namespace Swlib\Controller\Language\Service;

use Generate\Tables\Main\LanguageTable;
use Swlib\Connect\PoolMysqli;
use Swlib\Event\Attribute\Event;
use Swlib\Parse\ParseI18n;
use Swlib\Utils\Log;
use Throwable;

class LanguageService
{
    /**
     * 监听 language 表变更，重新生成静态文件
     */
    #[Event(name: LanguageTable::UpdateAfter)]
    #[Event(name: LanguageTable::InsertAfter)]
    #[Event(name: LanguageTable::DeleteAfter)]
    public function regenerateLanguageMap(): void
    {
        try {
            self::generateLanguageMapFromDb();
        } catch (Throwable $e) {
            Log::save("LanguageMap regenerate failed: " . $e->getMessage(), 'language_error');
        }
    }

    /**
     * 从数据库生成静态 LanguageMap 文件
     * @throws Throwable
     */
    private static function generateLanguageMapFromDb(): void
    {
        // 1. 获取表结构，确定有哪些语言字段
        $columns = self::getLanguageColumns();

        // 2. 查询所有数据
        $rows = PoolMysqli::query("SELECT * FROM `language`")->fetch_all(MYSQLI_ASSOC);

        // 3. 构建映射数组
        $map = array_fill_keys($columns, []);
        foreach ($rows as $row) {
            foreach ($columns as $lang) {
                if (!empty($row[$lang])) {
                    $map[$lang][$row['key']] = $row[$lang];
                }
            }
        }

        // 4. 生成 PHP 文件
        ParseI18n::generateLanguageMapFile($map);
    }

    /**
     * 获取语言字段列表（跳过 id, key, use_time）
     * @throws Throwable
     */
    private static function getLanguageColumns(): array
    {
        $info = PoolMysqli::query("DESCRIBE language")->fetch_all(MYSQLI_ASSOC);
        $skipFields = ['id', 'key', 'use_time'];
        $columns = [];
        foreach ($info as $col) {
            if (!in_array($col['Field'], $skipFields, true)) {
                $columns[] = $col['Field'];
            }
        }
        return $columns;
    }

}
