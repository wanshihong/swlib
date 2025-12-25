<?php
declare(strict_types=1);

namespace Swlib\Parse\ast;

use PhpParser\ParserFactory;
use ReflectionClass;
use Swlib\Utils\ConsoleColor;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;
use Throwable;

/**
 * AST 代码编译入口
 *
 * 扫描 App 和 Swlib 目录下的 PHP 文件，根据方法上的 Attribute
 * 在 runtime/Proxy 目录生成代理运行时代码。
 *
 * 支持的注解类型：
 * - AOP 切面（AspectInterface）
 * - 事务（Transaction）
 * - 协程（CoroutineAttribute）
 * - 队列（QueueAttribute）
 * - Task 进程（TaskAttribute）
 *
 * 生成 CallChainMap：
 * - 常量 KEY：K_App_Service_UserService__getUserInfo = 0
 * - CHAINS 数组：使用数字索引，O(1) 访问
 */
class AstCompiler
{
    /**
     * CallChainMap 结构：key 为常量名，value 为方法元数据
     */
    private array $map = [];


    public function __construct()
    {
        if (!class_exists(ParserFactory::class)) {
            ConsoleColor::writeErrorHighlight("AST编译警告: nikic/php-parser 未安装，跳过代理静态编译（请执行: composer require nikic/php-parser）");
            return;
        }

        // 先编译 App 目录，再编译 Swlib 目录
        $this->compileDir(ROOT_DIR . 'App');
        $this->compileDir(ROOT_DIR . 'Swlib');

        // 生成静态代理映射表
        if ($this->map !== []) {
            $this->dumpMapFile();
        }
    }

    /**
     * @param string $dir 根目录，如 ROOT_DIR . 'App' 或 ROOT_DIR . 'Swlib'
     */
    private function compileDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = File::eachDir($dir, function (string $filePath) {
            return str_ends_with($filePath, '.php');
        });

        foreach ($files as $filePath) {
            $className = str_replace(
                ['.php', ROOT_DIR, '/'],
                ['', '', '\\'],
                $filePath
            );

            // 类或 Trait 都可能包含代理注解
            if (!class_exists($className) && !trait_exists($className)) {
                continue;
            }

            try {
                $ref = new ReflectionClass($className);
                $classMap = new ClassWeaver($ref, $filePath)->weave();
                // ClassWeaver 返回的 map key 已经是常量 KEY 格式
                foreach ($classMap as $key => $meta) {
                    $this->map[$key] = $meta;
                }
            } catch (Throwable $e) {
                ConsoleColor::writeError("AST编译错误: 类 $className 代理编译失败 - {$e->getMessage()}");
            }
        }
    }

    private function dumpMapFile(): void
    {
        // 生成常量定义代码
        $constantsCode = '';
        $index = 0;

        foreach ($this->map as $constKey => $meta) {
            $constantsCode .= "    public const int $constKey = $index;\n";
            $index++;
        }

        // 生成 CHAINS 数组（使用 self::常量 作为 key）
        $chainsCode = "";
        foreach ($this->map as $constKey => $meta) {
            $metaExport = DataConverter::exportShort($meta);
            $chainsCode .= "        self::$constKey => $metaExport,\n";
        }


        $str = <<<PHP
<?php

declare(strict_types=1);

namespace Generate;

/**
 * 调用链映射表（编译时生成）
 *
 * 使用常量 KEY 作为索引，兼顾性能和可读性
 */
final class CallChainMap
{
    // 常量 KEY 定义
$constantsCode
    // 调用链配置
    public const array CHAINS = [
$chainsCode
    ];
}

PHP;

        File::save(
            RUNTIME_DIR . 'Generate/CallChainMap.php',
            $str
        );
    }

}

