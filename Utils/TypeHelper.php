<?php

namespace Swlib\Utils;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;
use Swlib\DataManager\ReflectionManager;
use Throwable;

/**
 * 类型助手工具类
 * 
 * 提供与对象属性类型相关的实用方法，包括：
 * - 根据属性类型获取默认值
 * - 类型检查和转换
 * - 属性类型分析
 */
class TypeHelper
{
    /**
     * 根据对象属性类型获取默认值
     *
     * 使用场景：
     * - 当需要为对象属性设置值，但值为 null 时
     * - 根据属性的类型声明自动提供合适的默认值
     * - 避免类型不匹配错误
     *
     * 支持的类型：
     * - 基础类型：string, int, float, bool, array
     * - 可为空类型：?string, ?int 等
     * - 联合类型：string|null, int|string 等
     *
     * 示例：
     * ```php
     * class User {
     *     public string $name;
     *     public ?int $age;
     *     public array $tags;
     * }
     *
     * $user = new User();
     * $defaultName = TypeHelper::getDefaultValueForProperty($user, 'name'); // ''
     * $defaultAge = TypeHelper::getDefaultValueForProperty($user, 'age');   // null
     * $defaultTags = TypeHelper::getDefaultValueForProperty($user, 'tags'); // []
     * ```
     *
     * @param object $object 目标对象实例
     * @param string $property 属性名称
     * @return mixed 属性类型对应的默认值
     * @throws ReflectionException
     */
    public static function getDefaultValueForProperty(object $object, string $property): mixed
    {
        $reflection = ReflectionManager::getClass($object);

        // 检查属性是否存在
        if (!$reflection->hasProperty($property)) {
            return null;
        }

        $reflectionProperty = $reflection->getProperty($property);
        $type = $reflectionProperty->getType();

        if ($type === null) {
            return null;
        }

        // 如果是联合类型，查找非 null 的类型
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType->getName() !== 'null') {
                    return self::getDefaultValueByTypeName($unionType->getName());
                }
            }
            return null;
        }

        // 如果是可为空类型，获取底层类型
        if ($type instanceof ReflectionNamedType) {
            if ($type->allowsNull()) {
                // 可为空类型，返回 null
                return null;
            }
            return self::getDefaultValueByTypeName($type->getName());
        }

        return null;
    }

    /**
     * 根据类型名称获取默认值
     * 
     * 支持的类型映射：
     * - string: 空字符串 ''
     * - int/integer: 0
     * - float/double: 0.0
     * - bool/boolean: false
     * - array: 空数组 []
     * - 其他类型: null
     * 
     * @param string $typeName 类型名称
     * @return mixed 对应类型的默认值
     */
    public static function getDefaultValueByTypeName(string $typeName): mixed
    {
        return match ($typeName) {
            'string' => '',
            'int', 'integer' => 0,
            'float', 'double' => 0.0,
            'bool', 'boolean' => false,
            'array' => [],
            default => null,
        };
    }

    /**
     * 检查属性是否允许为空
     *
     * @param object $object 目标对象实例
     * @param string $property 属性名称
     * @return bool true: 允许为空, false: 不允许为空
     * @throws ReflectionException
     */
    public static function isPropertyNullable(object $object, string $property): bool
    {
        $reflection = ReflectionManager::getClass($object);

        if (!$reflection->hasProperty($property)) {
            return true; // 属性不存在，认为可以为空
        }

        $reflectionProperty = $reflection->getProperty($property);
        $type = $reflectionProperty->getType();

        if ($type === null) {
            return true; // 没有类型声明，可以为空
        }

        // 联合类型中包含 null
        if ($type instanceof ReflectionUnionType) {
            return array_any($type->getTypes(), fn($unionType) => $unionType->getName() === 'null');
        }

        // 可为空的命名类型
        if ($type instanceof ReflectionNamedType) {
            return $type->allowsNull();
        }

        return true;
    }

    /**
     * 获取属性的类型名称
     *
     * @param object $object 目标对象实例
     * @param string $property 属性名称
     * @return string|null 类型名称，如果无法确定则返回 null
     * @throws ReflectionException
     */
    public static function getPropertyTypeName(object $object, string $property): ?string
    {
        $reflection = ReflectionManager::getClass($object);

        if (!$reflection->hasProperty($property)) {
            return null;
        }

        $reflectionProperty = $reflection->getProperty($property);
        $type = $reflectionProperty->getType();

        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            // 对于联合类型，返回第一个非 null 类型
            foreach ($type->getTypes() as $unionType) {
                if ($unionType->getName() !== 'null') {
                    return $unionType->getName();
                }
            }
        }

        return null;
    }

    /**
     * 安全地设置对象属性值
     * 
     * 如果值为 null 且属性不允许为空，则设置对应类型的默认值
     * 
     * @param object $object 目标对象
     * @param string $property 属性名称
     * @param mixed $value 要设置的值
     * @return bool 是否设置成功
     */
    public static function safeSetProperty(object $object, string $property, mixed $value): bool
    {
        try {
            // 如果值为 null，检查属性是否允许为空
            if ($value === null && !self::isPropertyNullable($object, $property)) {
                $value = self::getDefaultValueForProperty($object, $property);
            }
            
            $object->$property = $value;
            return true;
            
        } catch (Throwable) {
            return false;
        }
    }
} 