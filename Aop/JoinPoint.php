<?php
declare(strict_types=1);

namespace Swlib\Aop;

use OutOfBoundsException;
use ReflectionException;
use ReflectionMethod;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Exception\AppException;

/**
 * 连接点类
 *
 * 代表被拦截的方法调用，包含方法执行的所有上下文信息
 */
class JoinPoint
{
    /**
     * @var object|string 目标对象
     */
    public object|string $target {
        get {
            return $this->target;
        }
    }

    /**
     * @var string 方法名称
     */
    public string $methodName {
        get {
            return $this->methodName;
        }
    }

    /**
     * @var array 方法参数
     */
    public array $arguments {
        get {
            return $this->arguments;
        }
    }

    /**
     * @var ReflectionMethod|null 反射方法对象
     */
    private ?ReflectionMethod $reflectionMethod = null {
        /**
         * @throws ReflectionException
         */
        get {
            if ($this->reflectionMethod === null) {
                $this->reflectionMethod = new ReflectionMethod($this->target, $this->methodName);
            }
            return $this->reflectionMethod;
        }
    }

    /**
     * 构造函数
     *
     * @param object|string $target 目标对象
     * @param string $methodName 方法名称
     * @param array $arguments 方法参数
     */
    public function __construct(object|string $target, string $methodName, array $arguments = [])
    {
        $this->target = $target;
        $this->methodName = $methodName;
        $this->arguments = $arguments;
    }

    /**
     * 获取方法签名
     *
     * @return string
     */
    public function getSignature(): string
    {
        $className = is_object($this->target) ? get_class($this->target) : $this->target;
        return "$className::$this->methodName()";
    }

    /**
     * 获取指定索引的参数
     *
     * @param int $index 参数索引
     * @return mixed
     * @throws OutOfBoundsException|AppException
     */
    public function getArgument(int $index): mixed
    {
        if (!isset($this->arguments[$index])) {
            throw new AppException($index . LanguageEnum::NOT_FOUND);
        }
        return $this->arguments[$index];
    }

    /**
     * 设置指定索引的参数
     *
     * @param int $index 参数索引
     * @param mixed $value 参数值
     * @return void
     */
    public function setArgument(int $index, mixed $value): void
    {
        $arguments = $this->arguments;
        $arguments[$index] = $value;
        $this->arguments = $arguments;
    }

    /**
     * 获取方法返回类型
     *
     * @return string|null
     */
    public function getReturnType(): ?string
    {
        $returnType = $this->reflectionMethod->getReturnType();
        return $returnType?->getName();
    }

    /**
     * 获取参数信息
     *
     * @return array
     */
    public function getParameterInfo(): array
    {
        $params = [];
        foreach ($this->reflectionMethod->getParameters() as $param) {
            $params[] = [
                'name' => $param->getName(),
                'type' => $param->getType() ? $param->getType()->getName() : 'mixed',
                'optional' => $param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }
        return $params;
    }

    /**
     * 获取方法文档注释
     *
     * @return string|false
     */
    public function getDocComment(): string|false
    {
        return $this->reflectionMethod->getDocComment();
    }

    /**
     * 获取目标类名
     *
     * @return string
     */
    public function getTargetClass(): string
    {
        return is_object($this->target) ? get_class($this->target) : $this->target;
    }
}

