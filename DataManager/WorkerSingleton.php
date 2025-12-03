<?php

namespace Swlib\DataManager;

class WorkerSingleton
{
    protected static bool $initialized = false;

    protected function __construct()
    {
        // 私有构造函数，防止外部实例化
    }

    public static function getInstance(): static
    {
        $instanceKey = static::class;
        $instance = WorkerManager::get($instanceKey);
        if (!$instance) {
            $instance = new static();
            WorkerManager::set($instanceKey, $instance);
            $instance->_instance();
        }
        return $instance;
    }

    private function _instance(): void
    {
        if (!static::$initialized) {
            $this->initialize();
            static::$initialized = true;
        }
    }

    // 自定义初始化逻辑
    protected function initialize(): void
    {
        // 在这里添加你的初始化逻辑
    }

    // 禁止克隆
    private function __clone()
    {
    }
}
