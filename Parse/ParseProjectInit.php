<?php
declare(strict_types=1);

namespace Swlib\Parse;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\ProjectInit\Attribute\ProjectInitAttribute;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;

class ParseProjectInit
{
    public function __construct()
    {
        $entries = self::collectFromClasses(self::discoverClasses());
        File::save(RUNTIME_DIR . 'Generate/ProjectInitMap.php', self::generateMapContent($entries));
    }

    /**
     * @param array<int, string> $classes
     * @return array<int, array{run: array{0:string,1:string}, desc:string}>
     */
    public static function collectFromClasses(array $classes): array
    {
        $items = [];
        $errors = [];

        foreach ($classes as $className) {
            try {
                $reflector = new ReflectionClass($className);
            } catch (ReflectionException $e) {
                $errors[] = "项目初始化解析错误 {$className}: {$e->getMessage()}";
                continue;
            }

            $classAttributes = $reflector->getAttributes(ProjectInitAttribute::class);
            if (!empty($classAttributes)) {
                /** @var ProjectInitAttribute $attribute */
                $attribute = $classAttributes[0]->newInstance();
                $methodName = $attribute->method ?: 'handle';
                $errors = array_merge($errors, self::validateTarget($reflector, $methodName, true));
                if (!self::hasDuplicate($items, $className, $methodName)) {
                    $items[] = [
                        'run' => [$className, $methodName],
                        'desc' => $attribute->desc,
                    ];
                }
            }

            foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                $methodAttributes = $method->getAttributes(ProjectInitAttribute::class);
                if (empty($methodAttributes)) {
                    continue;
                }

                /** @var ProjectInitAttribute $attribute */
                $attribute = $methodAttributes[0]->newInstance();
                $errors = array_merge($errors, self::validateMethod($method));
                if (!self::hasDuplicate($items, $className, $method->getName())) {
                    $items[] = [
                        'run' => [$className, $method->getName()],
                        'desc' => $attribute->desc,
                    ];
                }
            }
        }

        if ($errors !== []) {
            ConsoleColor::writeErrorToStderr('ProjectInit 注解配置存在严重错误，已中止启动：');
            foreach ($errors as $index => $error) {
                ConsoleColor::writeErrorToStderr(sprintf('[%d] %s', $index + 1, $error));
            }
            throw new RuntimeException('ProjectInit 注解配置错误，已中止启动');
        }

        return $items;
    }

    /**
     * @param array<int, array{run: array{0:string,1:string}, desc:string}> $items
     */
    public static function generateMapContent(array $items): string
    {
        $itemsCode = DataConverter::exportShort($items);
        return <<<EOF
<?php

declare(strict_types=1);

namespace Generate;

class ProjectInitMap
{
    const array ITEMS = $itemsCode;
}
EOF;
    }

    /**
     * @return array<int, string>
     */
    private static function discoverClasses(): array
    {
        $files = array_merge(
            File::eachDir(SWLIB_DIR, static fn(string $filePath) => str_ends_with($filePath, '.php')),
            File::eachDir(APP_DIR, static fn(string $filePath) => str_ends_with($filePath, '.php'))
        );
        sort($files);

        $classes = [];
        foreach ($files as $file) {
            if (str_ends_with($file, 'ProjectInitAttribute.php')) {
                continue;
            }

            $className = str_replace(SWLIB_DIR, 'Swlib\\', $file);
            $className = str_replace([APP_DIR, ROOT_DIR, '.php'], ['App\\', '', ''], $className);
            $className = str_replace('/', '\\', $className);
            $classes[] = $className;
        }

        return $classes;
    }

    /**
     * @return array<int, string>
     */
    private static function validateTarget(ReflectionClass $reflector, string $methodName, bool $checkInstantiable): array
    {
        $errors = [];
        $className = $reflector->getName();

        if ($checkInstantiable && !$reflector->isInstantiable()) {
            $errors[] = "{$className} 不是可实例化类，不能用于项目初始化";
            return $errors;
        }

        if (!$reflector->hasMethod($methodName)) {
            $errors[] = "{$className} 类级 ProjectInit 注解要求定义 {$methodName}(): void 方法";
            return $errors;
        }

        return self::validateMethod($reflector->getMethod($methodName));
    }

    /**
     * @return array<int, string>
     */
    private static function validateMethod(ReflectionMethod $method): array
    {
        $errors = [];
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        if (!$method->isPublic()) {
            $errors[] = "{$className}::{$methodName} 必须是 public 方法";
        }
        if ($method->getNumberOfParameters() !== 0) {
            $errors[] = "{$className}::{$methodName} 参数数量必须为 0";
        }
        $returnType = $method->getReturnType();
        if (!$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'void' || $returnType->allowsNull()) {
            $errors[] = "{$className}::{$methodName} 返回类型必须显式声明为 void";
        }

        return $errors;
    }

    /**
     * @param array<int, array{run: array{0:string,1:string}, desc:string}> $items
     */
    private static function hasDuplicate(array $items, string $className, string $methodName): bool
    {
        foreach ($items as $item) {
            if ($item['run'][0] === $className && $item['run'][1] === $methodName) {
                return true;
            }
        }

        return false;
    }
}
