<?php

namespace Swlib\Table\Trait;

use Throwable;

/**
 * 变更追踪 Trait
 * 用于追踪 DTO 对象的字段修改情况，支持精准更新
 */
trait ChangeTrackingTrait
{
    /**
     * 原始数据（从数据库查询时的初始值）
     * 用于判断字段是否被修改
     */
    private array $__original = [];

    /**
     * 被修改的字段列表
     * key 是字段别名，value 是 true
     */
    private array $__modified = [];

    /**
     * 是否启用变更追踪
     * 用于在 fromArray 等场景下临时禁用追踪
     */
    private bool $__enableTracking = true;

    /**
     * 追踪字段修改（由 set hook 调用）
     * @param string $fieldAsName 字段别名
     * @param mixed $value 新值
     */
    public function __trackModification(string $fieldAsName, mixed $value): void
    {
        $this->__row[$fieldAsName] = $value;

        // 只有在启用追踪时才标记为修改
        if ($this->__enableTracking) {
            $this->__modified[$fieldAsName] = true;
        }
    }

    /**
     * 启用变更追踪
     */
    protected function enableTracking(): void
    {
        $this->__enableTracking = true;
    }

    /**
     * 禁用变更追踪
     */
    protected function disableTracking(): void
    {
        $this->__enableTracking = false;
    }

    /**
     * 检查是否启用了变更追踪
     */
    protected function isTrackingEnabled(): bool
    {
        return $this->__enableTracking;
    }

    /**
     * 标记字段为已修改
     * @param string $fieldAsName 字段别名
     */
    protected function markAsModified(string $fieldAsName): void
    {
        if ($this->__enableTracking) {
            $this->__modified[$fieldAsName] = true;
        }
    }

    /**
     * 检查字段是否被修改
     * @param string $fieldAsName 字段别名
     * @return bool
     */
    public function isFieldModified(string $fieldAsName): bool
    {
        return isset($this->__modified[$fieldAsName]) && $this->__modified[$fieldAsName];
    }

    /**
     * 获取所有被修改的字段名列表
     * @return array
     */
    public function getModifiedFields(): array
    {
        return array_keys(array_filter($this->__modified));
    }

    /**
     * 获取被修改的属性数据（只包含实际被设置或修改的属性）
     * @return array
     * @throws Throwable
     */
    public function getModifiedData(): array
    {
        $ret = [];

        // 遍历所有被标记为修改的字段
        foreach ($this->__modified as $asName => $flag) {
            if ($flag) {
                $ret[$asName] = $this->getByField($asName);
            }
        }

        return $ret;
    }

    /**
     * 设置原始数据（从数据库查询时调用）
     * @param array $data 原始数据
     */
    protected function setOriginalData(array $data): void
    {
        $this->__original = $data;
    }

    /**
     * 获取原始数据
     * @return array
     */
    public function getOriginalData(): array
    {
        return $this->__original;
    }

    /**
     * 清除所有修改标记
     */
    public function clearModifications(): void
    {
        $this->__modified = [];
    }

    /**
     * 重置为原始状态（恢复到查询时的状态）
     * @throws Throwable
     */
    public function reset(): void
    {
        if (!empty($this->__original)) {
            $this->disableTracking();
            foreach ($this->__original as $asName => $value) {
                $this->setByField($asName, $value, false);
            }
            $this->enableTracking();
            $this->clearModifications();
        }
    }
}

