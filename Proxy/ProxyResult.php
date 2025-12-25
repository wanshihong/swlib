<?php
declare(strict_types=1);

namespace Swlib\Proxy;

/**
 * 代理调度结果
 *
 * 存储单次 ProxyDispatcher::dispatch() 调用的链路信息：
 * - 各注解阶段的执行结果
 * - 真实方法的返回值
 * - 是否发生短路
 *
 * 使用示例：
 * ```php
 * $user = $service->getUserInfo($id);
 * $context = ProxyContext::pop();
 *
 * $queueId = $context->getProxyResult(QueueAttribute::class);
 * $cacheHit = $context->getProxyResult(CachingAspect::class);
 * $realResult = $context->getResult();
 * ```
 */
final class ProxyResult
{
    /**
     * 各注解阶段的执行结果
     * @var array<string, mixed> [注解类名 => 结果]
     */
    private array $proxyResults = [];

    /**
     * 真实方法的返回值
     */
    private mixed $result = null;

    /**
     * 是否已设置真实返回值
     */
    private bool $hasResult = false;

    /**
     * 是否发生短路（某个注解未调用 $next）
     */
    private bool $shortCircuited = false {
        get {
            return $this->shortCircuited;
        }
    }

    /**
     * 短路的注解类名
     */
    private ?string $shortCircuitedBy = null {
        get {
            return $this->shortCircuitedBy;
        }
    }

    /**
     * 设置某个注解的执行结果
     *
     * @param string $attributeClass 注解类名
     * @param mixed $result 执行结果
     * @return $this
     */
    public function setProxyResult(string $attributeClass, mixed $result): self
    {
        $this->proxyResults[$attributeClass] = $result;
        return $this;
    }

    /**
     * 获取某个注解的执行结果
     *
     * @param string $attributeClass 注解类名
     * @return mixed 如果不存在返回 null
     */
    public function getProxyResult(string $attributeClass): mixed
    {
        return $this->proxyResults[$attributeClass] ?? null;
    }

    /**
     * 检查某个注解是否有执行结果
     *
     * @param string $attributeClass 注解类名
     * @return bool
     */
    public function hasProxyResult(string $attributeClass): bool
    {
        return array_key_exists($attributeClass, $this->proxyResults);
    }

    /**
     * 获取所有注解的执行结果
     *
     * @return array<string, mixed>
     */
    public function getAllProxyResults(): array
    {
        return $this->proxyResults;
    }

    /**
     * 设置真实方法的返回值
     *
     * @param mixed $result
     * @return $this
     */
    public function setResult(mixed $result): self
    {
        $this->result = $result;
        $this->hasResult = true;
        return $this;
    }

    /**
     * 获取真实方法的返回值
     *
     * @return mixed
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * 是否已设置真实返回值
     *
     * @return bool
     */
    public function hasResult(): bool
    {
        return $this->hasResult;
    }

    /**
     * 标记发生短路
     *
     * @param string $attributeClass 导致短路的注解类名
     * @return $this
     */
    public function markShortCircuited(string $attributeClass): self
    {
        $this->shortCircuited = true;
        $this->shortCircuitedBy = $attributeClass;
        return $this;
    }

}

