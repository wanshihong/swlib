<?php
declare(strict_types=1);

namespace Swlib\TaskProcess;

use RuntimeException;
use Swlib\DataManager\WorkerManager;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Proxy\ProxyDispatcher;
use Swlib\Table\Db;
use Swoole\Server;
use Throwable;

/**
 * Task 进程调度器
 *
 * 将任务投递到 Swoole Task 进程执行
 * 由 ProxyDispatcher 调用，支持 #[TaskAttribute] 注解
 */
final class TaskDispatcher
{
    /**
     * 快捷方法：投递任务到 Task 进程
     *
     * @param array $callable 执行的数组 [类名, 方法名]
     * @param array $arguments 传递的可序列化参数
     * @throws Throwable
     *
     * @example
     * TaskDispatcher::dispatchTask(
     *     [UserService::class, 'processUser'],
     *     [123, ['name' => 'John']]
     * );
     */
    public static function dispatchTask(array $callable, array $arguments = []): void
    {
        if (count($callable) !== 2 || !is_string($callable[0]) || !is_string($callable[1])) {
            // callable参数必须是数组格式
            throw new AppException('callable' . AppErr::PARAM_ERROR);
        }

        [$className, $methodName] = $callable;

        self::dispatch([
            'class' => $className,
            'method' => $methodName,
            'arguments' => $arguments,
            'isStatic' => false,
        ]);
    }

    /**
     * 投递任务到 Task 进程
     *
     * @param array $data 任务数据
     *   - class: 类名
     *   - method: 方法名（带 __proxy 后缀）
     *   - arguments: 参数数组
     *   - isStatic: 是否静态方法
     *   - transaction?: 事务配置（如有）
     * @throws Throwable
     */
    public static function dispatch(array $data): void
    {
        // 使用 serialize 验证数据是否可序列化（Swoole task 使用 PHP 序列化传递数据）
        self::validateSerializable($data['arguments'] ?? []);

        /** @var Server $server */
        $server = WorkerManager::get('server');
        if ($server === null) {
            // Swoole Server未初始化
            throw new RuntimeException('server' . AppErr::NOT_INIT);
        }

        // 如果已经在 Task 进程中，直接执行，避免死锁
        if ($server->taskworker) {
            ProxyDispatcher::invokeMethod(
                $data['class'],
                $data['method'],
                $data['arguments'],
                $data['isStatic'] ?? false
            );
            return;
        }

        $server->task($data);
    }

    /**
     * 在 Task 进程中执行任务
     *
     * 由 OnTaskEvent 调用
     * @throws Throwable
     */
    public static function execute(array $data): mixed
    {
        $className = $data['class'];
        $method = $data['method'];
        $arguments = $data['arguments'];
        $isStatic = $data['isStatic'] ?? false;
        $txConfig = $data['transaction'] ?? null;

        // 如果有事务配置，在事务内执行
        if ($txConfig !== null) {
            $txArgs = $txConfig['arguments'] ?? [];
            return Db::transaction(
                call: static fn() => ProxyDispatcher::invokeMethod($className, $method, $arguments, $isStatic),
                dbName: $txArgs['dbName'] ?? 'default',
                isolationLevel: $txArgs['isolationLevel'] ?? null,
                timeout: $txArgs['timeout'] ?? null,
                enableLog: $txArgs['logTransaction'] ?? false
            );
        }

        // 直接执行
        return ProxyDispatcher::invokeMethod($className, $method, $arguments, $isStatic);
    }

    /**
     * 验证数据是否可序列化
     *
     * Swoole task 使用 PHP serialize 传递数据，不支持：
     * - 资源类型（MySQL/Redis连接、文件句柄等）
     * - 闭包（匿名函数）
     * - 包含上述类型的对象
     *
     * @throws AppException
     */
    private static function validateSerializable(mixed $data): void
    {
        try {
            serialize($data);
        } catch (Throwable $e) {
            // Task参数包含不可序列化的数据类型
            throw new AppException(AppErr::PARAM_ERROR . ": " . $e->getMessage());
        }
    }
}

