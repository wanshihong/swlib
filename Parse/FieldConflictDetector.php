<?php
declare(strict_types=1);

namespace Swlib\Parse;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Swlib\Table\Trait\FuncTrait;
use Swlib\Table\Trait\RawQueryTrait;
use Swlib\Table\Trait\SqlTrait;
use Swlib\Table\Trait\TableEventTrait;
use Swlib\Table\Trait\TableDtoTrait;
use Swlib\Table\Trait\TableDataListsTrait;
use Swlib\Utils\StringConverter;

/**
 * 字段冲突检测器
 * 
 * 检测数据库字段名是否与 Table/DTO 类的方法、属性、常量冲突
 */
class FieldConflictDetector
{
    /**
     * Table 类使用的 Trait 列表
     */
    private const array TABLE_TRAITS = [
        FuncTrait::class,
        RawQueryTrait::class,
        SqlTrait::class,
        TableEventTrait::class,
    ];

    /**
     * DTO 类使用的 Trait 列表
     */
    private const array DTO_TRAITS = [
        TableDtoTrait::class,
        TableDataListsTrait::class,
    ];

    /**
     * Table 类生成的常量（需要手动维护）
     */
    private const array TABLE_GENERATED_CONSTANTS = [
        'DATABASES',
        'TABLE_NAME',
        'DTO_CLASS',
        'PRI_KEY',
        'FIELD_ALL',
        'DTO_DEFAULT',
        'DB_DEFAULT',
        'DB_ALLOW_NULL',
        'AS_FIELD',
        // 事件常量
        'SelectBefore',
        'SelectAfter',
        'InsertBefore',
        'InsertAfter',
        'UpdateBefore',
        'UpdateAfter',
        'DeleteBefore',
        'DeleteAfter',
    ];

    /**
     * Table 类生成的方法（需要手动维护）
     */
    private const array TABLE_GENERATED_METHODS = [
        'getDto',
        'selectOne',
        'selectAll',
    ];

    /**
     * DTO 类生成的常量（需要手动维护）
     */
    private const array DTO_GENERATED_CONSTANTS = [
        'TABLE_CLASS',
    ];

    /**
     * DTO 类生成的私有属性（需要手动维护）
     */
    private const array DTO_GENERATED_PROPERTIES = [
        '__row',
        '__rows',
    ];

    /**
     * DTO 类生成的方法（需要手动维护）
     */
    private const array DTO_GENERATED_METHODS = [
        'getIterator',
    ];

    /**
     * 缓存：Table Trait 的所有公共和受保护方法名
     */
    private static ?array $tableTraitMethods = null;

    /**
     * 缓存：DTO Trait 的所有公共方法名
     */
    private static ?array $dtoTraitMethods = null;

    /**
     * 检测字段冲突
     *
     * @param array $fields 字段列表
     * @return array 冲突列表 ['table' => [...], 'dto' => [...]]
     * @throws ReflectionException
     */
    public static function detect(array $fields): array
    {
        $conflicts = [
            'table' => [],
            'dto' => [],
        ];

        foreach ($fields as $field) {
            $fieldName = $field['Field'];

            // 检测 Table 类冲突
            $tableConflicts = self::detectTableConflicts($fieldName);
            if (!empty($tableConflicts)) {
                $conflicts['table'][$fieldName] = $tableConflicts;
            }

            // 检测 DTO 类冲突
            $dtoConflicts = self::detectDtoConflicts($fieldName);
            if (!empty($dtoConflicts)) {
                $conflicts['dto'][$fieldName] = $dtoConflicts;
            }
        }

        return $conflicts;
    }

    /**
     * 检测 Table 类冲突
     *
     * @param string $fieldName 数据库字段名
     * @return array 冲突项列表
     * @throws ReflectionException
     */
    private static function detectTableConflicts(string $fieldName): array
    {
        $conflicts = [];

        // 转换为 Table 常量格式（大写下划线）
        $constantName = strtoupper($fieldName);

        // 检测常量冲突
        if (in_array($constantName, self::TABLE_GENERATED_CONSTANTS, true)) {
            $conflicts[] = "常量 $constantName";
        }

        // 检测方法冲突（Trait 方法）
        $traitMethods = self::getTableTraitMethods();
        if (in_array($constantName, $traitMethods, true)) {
            $conflicts[] = "Trait 方法 $constantName";
        }

        // 检测生成的方法冲突
        if (in_array($constantName, self::TABLE_GENERATED_METHODS, true)) {
            $conflicts[] = "生成的方法 $constantName";
        }

        return $conflicts;
    }

    /**
     * 检测 DTO 类冲突
     *
     * @param string $fieldName 数据库字段名
     * @return array 冲突项列表
     * @throws ReflectionException
     */
    private static function detectDtoConflicts(string $fieldName): array
    {
        $conflicts = [];

        // 转换为 DTO 属性格式（小驼峰）
        $propertyName = StringConverter::underscoreToCamelCase($fieldName, '_', false);

        // 检测常量冲突
        $constantName = strtoupper($fieldName);
        if (in_array($constantName, self::DTO_GENERATED_CONSTANTS, true)) {
            $conflicts[] = "常量 $constantName";
        }

        // 检测私有属性冲突（需要检查原始字段名和转换后的属性名）
        if (in_array($fieldName, self::DTO_GENERATED_PROPERTIES, true)) {
            $conflicts[] = "私有属性 $fieldName";
        }
        if (in_array($propertyName, self::DTO_GENERATED_PROPERTIES, true)) {
            $conflicts[] = "私有属性 $propertyName";
        }

        // 检测方法冲突（Trait 方法）
        $traitMethods = self::getDtoTraitMethods();
        if (in_array($propertyName, $traitMethods, true)) {
            $conflicts[] = "Trait 方法 $propertyName";
        }

        // 检测生成的方法冲突
        if (in_array($propertyName, self::DTO_GENERATED_METHODS, true)) {
            $conflicts[] = "生成的方法 $propertyName";
        }

        return $conflicts;
    }

    /**
     * 获取 Table Trait 的所有公共和受保护方法名（缓存）
     *
     * @return array 方法名数组（大写下划线格式）
     * @throws ReflectionException
     */
    private static function getTableTraitMethods(): array
    {
        if (self::$tableTraitMethods !== null) {
            return self::$tableTraitMethods;
        }

        $methods = [];
        foreach (self::TABLE_TRAITS as $traitClass) {
            $reflection = new ReflectionClass($traitClass);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
                // 转换为大写下划线格式
                $methodName = $method->getName();
                $methods[] = strtoupper(StringConverter::camelCaseToUnderscore($methodName));
            }
        }

        self::$tableTraitMethods = array_unique($methods);
        return self::$tableTraitMethods;
    }

    /**
     * 获取 DTO Trait 的所有公共方法名（缓存）
     *
     * @return array 方法名数组（小驼峰格式）
     * @throws ReflectionException
     */
    private static function getDtoTraitMethods(): array
    {
        if (self::$dtoTraitMethods !== null) {
            return self::$dtoTraitMethods;
        }

        $methods = [];
        foreach (self::DTO_TRAITS as $traitClass) {
            $reflection = new ReflectionClass($traitClass);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $methods[] = $method->getName();
            }
        }

        self::$dtoTraitMethods = array_unique($methods);
        return self::$dtoTraitMethods;
    }
}

