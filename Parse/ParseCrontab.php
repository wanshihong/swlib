<?php

namespace Swlib\Parse;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Swlib\Crontab\Attribute\CrontabAttribute;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;

/**
 * Crontab 编译时解析器
 *
 * 扫描所有 PHP 文件，查找 CrontabAttribute 注解，生成 CrontabMap.php
 */
class ParseCrontab
{
    public function __construct()
    {
        $this->parse();
    }

    /**
     * 解析 Crontab 注解
     */
    private function parse(): void
    {
        // 扫描 Swlib 和 App 目录下的所有 PHP 文件
        $filesLib = File::eachDir(SWLIB_DIR, function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $filesApp = File::eachDir(ROOT_DIR . 'App', function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $files = array_merge($filesLib, $filesApp);

        $items = [];
        foreach ($files as $file) {
            // 跳过 CrontabAttribute.php 文件本身
            if (str_ends_with($file, 'CrontabAttribute.php')) {
                continue;
            }

            $file = str_replace(SWLIB_DIR, 'Swlib\\', $file);
            $className = str_replace([ROOT_DIR, '.php'], '', $file);
            $className = str_replace("/", '\\', $className);

            try {
                $reflector = new ReflectionClass($className);

                // 解析类级别的注解
                $classAttributes = $reflector->getAttributes(CrontabAttribute::class);
                if (!empty($classAttributes)) {
                    /** @var CrontabAttribute $attr */
                    $attr = $classAttributes[0]->newInstance();
                    $items[] = [
                        'run' => [$className, 'handle'],
                        'cron' => $attr->cron,
                        'timeout' => $attr->timeout,
                        'enable_coroutine' => $attr->enable_coroutine,
                        'name' => $attr->name ?: "$className::handle",
                    ];
                }

                // 解析方法级别的注解
                $methods = $reflector->getMethods(ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $methodAttributes = $method->getAttributes(CrontabAttribute::class);
                    if (!empty($methodAttributes)) {
                        /** @var CrontabAttribute $attr */
                        $attr = $methodAttributes[0]->newInstance();
                        $items[] = [
                            'run' => [$className, $method->getName()],
                            'cron' => $attr->cron,
                            'timeout' => $attr->timeout,
                            'enable_coroutine' => $attr->enable_coroutine,
                            'name' => $attr->name ?: "$className::" . $method->getName(),
                        ];
                    }
                }
            } catch (ReflectionException $e) {
                ConsoleColor::writeError("Crontab 解析错误: {$e->getMessage()}");
            }
        }

        // 生成 CrontabMap.php
        $this->generateCrontabMap($items);
    }

    /**
     * 生成 CrontabMap.php 文件
     */
    private function generateCrontabMap(array $items): void
    {
        $mapContent = <<<'EOF'
<?php

declare(strict_types=1);

namespace Generate;

/**
 * Crontab 任务映射表
 * 
 * 自动生成，请勿手动修改
 */
class CrontabMap
{
    const array TASKS = %s;
}
EOF;

        $tasksCode = DataConverter::exportShort($items, true);
        $content = sprintf($mapContent, $tasksCode);

        File::save(RUNTIME_DIR . 'Generate/CrontabMap.php', $content);
    }
}

