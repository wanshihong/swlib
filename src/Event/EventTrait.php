<?php

namespace Swlib\Event;

use Exception;
use Generate\EventMap;
use Swlib\Utils\Log;
use ReflectionClass;
use Throwable;

trait EventTrait
{
    private static array $listeners = [];

    /**
     * 注册事件监听器
     *
     * @param string $event 事件名称
     *
     * array 数组的第一个元素是类名，第二个元素是方法名
     * callable 就是一个匿名函数
     * @param array|callable $listener
     * @throws Exception
     */
    public static function on(string $event, array|callable $listener): void
    {
        if (is_array($listener)) {
            if (count($listener) !== 2) {
                throw new Exception("listener 参数错误 需要 [className,methodName]");
            }
            [$className, $method] = $listener;

            // 检查类是否存在
            if (!class_exists($className)) {
                throw new Exception("listener 参数错误: 类 $className 不存在");
            }

            // 使用类反射判断是否存在该方法
            $reflection = new ReflectionClass($className);
            if (!$reflection->hasMethod($method)) {
                throw new Exception("listener 参数错误: 方法 $method 不存在于类 $className 中");
            }

        }

        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        self::$listeners[$event][] = $listener;
    }

    /**
     * 移除事件监听器
     *
     * @param string $event 事件名称
     * @param callable|array $listener 监听器回调函数
     */
    public static function off(string $event, callable|array $listener): void
    {
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $index => $currentListener) {
                if (self::isSameListener($listener, $currentListener)) {
                    unset(self::$listeners[$event][$index]);
                }
            }
            if (empty(self::$listeners[$event])) {
                unset(self::$listeners[$event]);
            }
        }
    }

    /**
     * 触发事件
     *
     * @param string $event 事件名称
     * @param array $args 传递给监听器的参数
     */
    public static function emit(string $event, array $args = []): void
    {
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $listener) {
                 self::run($listener, $args);
            }
        }
    }


    /**
     * 比较两个监听器是否相同
     * @param $listener1
     * @param $listener2
     * @return bool
     */
    private static function isSameListener($listener1, $listener2): bool
    {
        if (is_callable($listener1) && is_callable($listener2)) {
            return $listener1 === $listener2;
        }
        if (is_array($listener1) && is_array($listener2)) {
            return $listener1 === $listener2;
        }
        return false;
    }


    /**
     * 执行监听器 ; 就在本协程内执行，
     * 否则发生协程切换，读取不到到协程上下文，监听事件的地方就会报错
     * 需要异步执行的化，请在事件监听的地方 新建协程 或者 分发到 task 进程执行
     * @param array|callable $listener
     * @param array $args
     * @return void
     */
    private static function run(array|callable $listener, array $args): void
    {
        try {
            if (is_callable($listener)) {
                call_user_func_array($listener, $args);
            } elseif (is_array($listener)) {
                [$object, $method] = $listener;
                new $object()->$method($args);
            }
        } catch (Throwable $e) {
            Log::saveException($e, 'event');
        }
    }


    public static function onMaps(): void
    {
        foreach (EventMap::EVENTS as $event) {
            try {
                Event::on($event['name'], $event['run']);
            } catch (Exception $e) {
                Log::saveException($e, 'event-on');
            }
        }
    }


    public static function offMaps(): void
    {
        foreach (EventMap::EVENTS as $event) {
            Event::off($event['name'], $event['run']);
        }
    }

}
