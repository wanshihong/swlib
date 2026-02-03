<?php

declare(strict_types=1);

namespace Swlib\Parse;

use Generate\DatabaseConnect;
use ReflectionClass;
use Swlib\Attribute\I18nAttribute;
use Swlib\Controller\Language\Interface\LanguageInterface;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;
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
        // 自动扫描实现 LanguageInterface 的类
        $classes = self::scanLanguageInterfaceClasses();
        echo "扫描到 " . count($classes) . " 个 LanguageInterface 实现类" . PHP_EOL;

        $stats = [
            'inserted' => 0, // 新增数量
            'updated' => 0,  // 更新数量
            'skipped' => 0,  // 跳过数量（解析失败）
        ];

        // 收集数据用于生成静态文件
        $mapData = [];

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

                // 收集数据用于生成静态文件
                foreach ($langData as $lang => $value) {
                    $mapData[$lang][$key] = $value;
                }
            }
        }

        // 生成静态文件
        self::generateLanguageMapFile($mapData);

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
     * 采用不覆盖策略：已存在的翻译不会被覆盖
     *
     * @param string $key i18n key
     * @param array $langData ['lang_code' => 'translation', ...]
     * @param array &$stats 统计数据
     * @throws Throwable
     */
    private static function writeToLanguageTable(string $key, array $langData, array &$stats): void
    {
        $keyEscaped = addslashes($key);
        $time = time();

        // 先检查 key 是否存在
        $existing = DatabaseConnect::query("SELECT * FROM `language` WHERE `key` = '$keyEscaped' LIMIT 1")->fetch_assoc();

        try {
            if (empty($existing)) {
                // 不存在，插入新记录
                $insertFields = ['`key`', '`use_time`'];
                $insertValues = ["'$keyEscaped'", $time];

                foreach ($langData as $lang => $value) {
                    $valueEscaped = addslashes($value);
                    $insertFields[] = "`$lang`";
                    $insertValues[] = "'$valueEscaped'";
                }

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
                // 已存在，只更新不存在的语言字段
                $updateFields = [];
                foreach ($langData as $lang => $value) {
                    if (empty($existing[$lang])) {
                        $valueEscaped = addslashes($value);
                        $updateFields[] = "`$lang` = '$valueEscaped'";
                    }
                }

                if (!empty($updateFields)) {
                    $updateFields[] = "`use_time` = $time";
                    $id = (int)$existing['id'];
                    $sql = "UPDATE `language` SET " . implode(', ', $updateFields) . " WHERE `id` = $id";
                    DatabaseConnect::query($sql);
                    $stats['updated']++;
                }
            }
        } catch (Throwable $e) {
            echo "  [失败] $key: " . $e->getMessage() . PHP_EOL;
            $stats['skipped']++;
        }
    }

    /**
     * 扫描指定目录下实现 LanguageInterface 的所有类
     * @return array 类名数组
     */
    private static function scanLanguageInterfaceClasses(): array
    {
        $classes = [];

        // 扫描 App 目录
        $appFiles = File::eachDir(ROOT_DIR . "App", function ($filePath) {
            return str_ends_with($filePath, '.php');
        });

        // 扫描 Swlib 目录
        $libFiles = File::eachDir(ROOT_DIR . "Swlib", function ($filePath) {
            return str_ends_with($filePath, '.php');
        });

        $files = array_merge($appFiles, $libFiles);

        foreach ($files as $file) {
            $className = self::extractClassNameFromFile($file);
            if ($className && self::implementsLanguageInterface($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * 从文件路径提取类名
     */
    private static function extractClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if (!preg_match('/namespace\s+([\w\\\\]+);/i', $content, $namespaceMatch)) {
            return null;
        }

        if (!preg_match('/(?:abstract|final\s+)?class\s+(\w+)\s+/i', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }

    /**
     * 检查类是否实现 LanguageInterface
     */
    private static function implementsLanguageInterface(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $interfaces = class_implements($className);
        return isset($interfaces[LanguageInterface::class]);
    }

    /**
     * 生成 LanguageMap 静态文件
     * @param array $mapData ['zh' => ['key' => '翻译'], 'en' => ['key' => 'translation']]
     */
    private static function generateLanguageMapFile(array $mapData): void
    {
        $content = "<?php\n\nnamespace Generate;\n\n";
        $content .= "/**\n";
        $content .= " * 多语言静态映射表\n";
        $content .= " * 此文件由 ParseI18n 自动生成，请勿手动修改\n";
        $content .= " */\n";
        $content .= "class LanguageMap\n{\n";
        $content .= "    /**\n";
        $content .= "     * @var array ['zh' => ['key' => '翻译'], 'en' => ['key' => 'translation']]\n";
        $content .= "     */\n";
        $content .= "    public static array \$map = " . DataConverter::exportShort($mapData) . ";\n";
        $content .= "}\n";

        File::save(ROOT_DIR . "runtime/Generate/LanguageMap.php", $content);
        echo "LanguageMap 文件已生成: " . ROOT_DIR . "runtime/Generate/LanguageMap.php" . PHP_EOL;
    }

}
