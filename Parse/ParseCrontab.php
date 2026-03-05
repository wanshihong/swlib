<?php

namespace Swlib\Parse;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
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
        $errors = [];
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
                    $classErrors = $this->validateClassLevelTask($reflector);
                    $errors = array_merge($errors, $classErrors);
                    /** @var CrontabAttribute $attr */
                    $attr = $classAttributes[0]->newInstance();
                    if (empty($classErrors)) {
                        $items[] = [
                            'run' => [$className, 'handle'],
                            'cron' => $attr->cron,
                            'timeout' => $attr->timeout,
                            'enable_coroutine' => $attr->enable_coroutine,
                            'name' => $attr->name ?: "$className::handle",
                        ];
                    }
                }

                // 解析方法级别的注解
                $methods = $reflector->getMethods();
                foreach ($methods as $method) {
                    if ($method->getDeclaringClass()->getName() !== $className) {
                        continue;
                    }
                    $methodAttributes = $method->getAttributes(CrontabAttribute::class);
                    if (!empty($methodAttributes)) {
                        $methodErrors = $this->validateMethodLevelTask($reflector, $method);
                        if (!empty($methodErrors)) {
                            $errors = array_merge($errors, $methodErrors);
                            continue;
                        }
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
                $errors[] = "Crontab 解析错误 {$className}: {$e->getMessage()}";
            }
        }

        if (!empty($errors)) {
            ConsoleColor::writeErrorToStderr('Crontab 注解配置存在严重错误，已中止启动：');
            foreach ($errors as $index => $error) {
                ConsoleColor::writeErrorToStderr(sprintf('[%d] %s', $index + 1, $error));
            }
            throw new RuntimeException('Crontab 注解配置错误，已中止启动');
        }

        // 生成 CrontabMap.php
        $this->generateCrontabMap($items);
    }

    /**
     * 校验类级注解任务签名：
     * public function handle($server): void
     *
     * @return array<int,string> 校验错误列表
     */
    private function validateClassLevelTask(ReflectionClass $reflector): array
    {
        $className = $reflector->getName();
        $errors = [];

        if (!method_exists($className, 'handle')) {
            $errors[] = "$className 类级 Crontab 注解要求定义 handle(\$server): void 方法";
            return $errors;
        }

        $method = $reflector->getMethod('handle');

        if (!$method->isPublic()) {
            $errors[] = "$className::handle 必须是 public 方法";
        }

        if ($method->getNumberOfParameters() !== 1) {
            $errors[] = "$className::handle 参数数量必须为 1（签名：handle(\$server): void）";
        }

        if (!$this->isVoidReturnType($method)) {
            $errors[] = "$className::handle 返回类型必须显式声明为 void";
        }

        return $errors;
    }

    /**
     * 校验方法级注解任务签名：
     * public function xxx($server): void
     *
     * @return array<int,string> 校验错误列表
     */
    private function validateMethodLevelTask(ReflectionClass $reflector, ReflectionMethod $method): array
    {
        $className = $reflector->getName();
        $methodName = $method->getName();
        $errors = [];

        if (!$method->isPublic()) {
            $errors[] = "$className::$methodName 必须是 public 方法";
        }

        if ($method->getNumberOfParameters() !== 1) {
            $errors[] = "$className::$methodName 参数数量必须为 1（签名：{$methodName}(\$server): void）";
        }

        if (!$this->isVoidReturnType($method)) {
            $errors[] = "$className::$methodName 返回类型必须显式声明为 void";
        }

        return $errors;
    }

    private function isVoidReturnType(ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();
        if (!$returnType instanceof ReflectionNamedType) {
            return false;
        }

        return $returnType->getName() === 'void' && !$returnType->allowsNull();
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
