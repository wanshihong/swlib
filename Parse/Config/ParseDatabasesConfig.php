<?php

declare(strict_types=1);

namespace Swlib\Parse\Config;

use Generate\DatabaseConnect;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;
use Throwable;

/**
 * Config 解析器
 * 从数据库读取所有配置并生成静态 ConfigMap 文件
 */
class ParseDatabasesConfig
{
    /**
     * @throws Throwable
     */
    public function __construct()
    {
        self::generateConfigMapFromDb();
        echo "ConfigMap 文件已生成: " . ROOT_DIR . "runtime/Generate/ConfigMap.php" . PHP_EOL;
    }

    /**
     * 从数据库生成静态 ConfigMap 文件
     * @throws Throwable
     */
    private static function generateConfigMapFromDb(): void
    {
        // 查询所有配置
        $rows = DatabaseConnect::query("SELECT * FROM `config`")->fetch_all(MYSQLI_ASSOC);

        // 构建配置数组
        $configs = [];
        foreach ($rows as $row) {
            $configs[$row['key']] = [
                'value' => $row['value'],
                'is_enable' => (int)$row['is_enable'],
                'value_type' => $row['value_type'],
                'desc' => $row['desc'],
            ];
        }

        // 写入静态文件
        self::writeConfigMapFile($configs);
    }

    /**
     * 写入 ConfigMap 文件
     */
    private static function writeConfigMapFile(array $configs): void
    {
        $content = "<?php\n\nnamespace Generate;\n\n";
        $content .= "/**\n";
        $content .= " * 配置静态映射表\n";
        $content .= " * 此文件由系统自动生成，请勿手动修改\n";
        $content .= " */\n";
        $content .= "class ConfigMap\n{\n";
        $content .= "    /**\n";
        $content .= "     * 配置数据 [key => ['value' => ..., 'is_enable' => ..., 'value_type' => ..., 'desc' => ...]]\n";
        $content .= "     */\n";
        $content .= "    public static array \$configs = " . DataConverter::exportShort($configs) . ";\n";
        $content .= "}\n";

        File::save(ROOT_DIR . "runtime/Generate/ConfigMap.php", $content);
    }
}
