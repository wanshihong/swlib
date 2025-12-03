<?php

namespace Swlib\Event\Attribute;

use Attribute;
use Swlib\Event\Trait\EventTrait;


/**
 * 事件管理器
 *
 * 为什么需要事件管理，
 * 因为相同的一个事件， 可能在多个地方需要执行不同的逻辑，达到解耦的目的
 * 如果不用事件，就只能逻辑硬编码到一起
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)] class Event
{
    use EventTrait;

    /**
     * 构造函数执行用来定义绑定事件用的
     * 项目启动会扫描所有的 EventManager 注解，
     * 然后在 OnWorkerStartEvent 中调用 EventManager::on() 注册事件监听器
     * 并且在 OnWorkerStopEvent 中调用 EventManager::off() 移除事件监听器
     *
     * @param string $name 事件名称
     * @param int $priority 事件执行的优先级，数字越小优先级越高，默认为10
     */
    public function __construct(public string $name, public int $priority = 10)
    {
    }


}
