<?php

declare(strict_types=1);

namespace Swlib\Controller\Language\Service;

use Generate\DatabaseConnect;
use Generate\Tables\Main\LanguageTable;
use Swlib\Event\Attribute\Event;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;
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
        $rows = DatabaseConnect::query("SELECT * FROM `language`")->fetch_all(MYSQLI_ASSOC);

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
        self::writeLanguageMapFile($map);
    }

    /**
     * 获取语言字段列表（跳过 id, key, use_time）
     * @throws Throwable
     */
    private static function getLanguageColumns(): array
    {
        $info = DatabaseConnect::query("DESCRIBE language")->fetch_all(MYSQLI_ASSOC);
        $skipFields = ['id', 'key', 'use_time'];
        $columns = [];
        foreach ($info as $col) {
            if (!in_array($col['Field'], $skipFields, true)) {
                $columns[] = $col['Field'];
            }
        }
        return $columns;
    }

    /**
     * 写入 LanguageMap 文件
     */
    private static function writeLanguageMapFile(array $map): void
    {

        $content = "<?php\n\nnamespace Generate;\n\n";
        $content .= "/**\n";
        $content .= " * 多语言静态映射表\n";
        $content .= " * 此文件由系统自动生成，请勿手动修改\n";
        $content .= " */\n";
        $content .= "class LanguageMap\n{\n";
        $content .= "    /**\n";
        $content .= "     * @var array ['zh' => ['key' => '翻译'], 'en' => ['key' => 'translation']]\n";
        $content .= "     */\n";
        $content .= "    public static array \$map = " . DataConverter::exportShort($map) . ";\n";
        $content .= "}\n";

        File::save(ROOT_DIR . "runtime/Generate/LanguageMap.php", $content);
    }
}
