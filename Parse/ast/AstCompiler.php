<?php
declare(strict_types=1);

namespace Swlib\Parse\ast;

use PhpParser\ParserFactory;
use ReflectionClass;
use Swlib\Utils\File;
use Throwable;

/**
 * AST 代码编译入口
 *
 * 扫描 App 目录下的 PHP 文件，根据方法上的 Attribute（实现 AspectInterface
 * 或 Transaction）在 runtime/App 目录生成带 AOP 生命周期的运行时代码。
 */
class AstCompiler
{
    /**
     * @var array<string, array<string, array{aspects: string[], transaction: ?string}>>
     */
    private array $map = [];


    public function __construct()
    {
        if (!class_exists(ParserFactory::class)) {
            echo "[AST] nikic/php-parser 未安装，跳过 AOP 静态编译（请 composer require nikic/php-parser）" . PHP_EOL;
            return;
        }

        // 先编译 App 目录，再编译 Swlib 目录
        $this->compileDir(ROOT_DIR . 'App');
        $this->compileDir(ROOT_DIR . 'Swlib');

        // 生成静态切面映射表
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

            // 类或 Trait 都可能包含切面注解
            if (!class_exists($className) && !trait_exists($className)) {
                continue;
            }

            try {
                $ref = new ReflectionClass($className);
                $classMap = new ClassWeaver($ref, $filePath)->weave();
                if ($classMap !== []) {
                    $this->map[$className] = $classMap;
                }
            } catch (Throwable $e) {
                echo "[AST] compile AOP failed for $className: {$e->getMessage()}" . PHP_EOL;
            }
        }
    }

    private function dumpMapFile(): void
    {
        $map = var_export($this->map, true);

        $str = <<<PHP
<?php

declare(strict_types=1);

namespace Generate;


final class ProxyMap
{

    const array MAP = $map;


}

PHP;

        File::save(
            RUNTIME_DIR . 'Generate/ProxyMap.php',
            $str
        );
    }
}

