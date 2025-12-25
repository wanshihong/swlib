<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Event\EventEnum;
use Swlib\TaskProcess\TaskDispatcher;
use Swlib\Utils\Log;
use Swoole\Server;
use Swoole\Server\Task;
use Throwable;

/**
 * Swoole Task 事件处理器
 *
 * 处理两种格式的任务数据：
 * 1. 新格式（ProxyDispatcher）：['class' => ..., 'method' => ..., 'arguments' => ...]
 * 2. 旧格式（已废弃）：['action' => [...], 'data' => [...]]
 */
class OnTaskEvent
{
    public Server $server;
    public Task $task;

    public function handle(Server $server, Task $task): void
    {
        $this->server = $server;
        $this->task = $task;
        $data = $task->data;
        $ret = null;

        try {
            // 新格式：由 ProxyDispatcher/TaskDispatcher 投递
            if (isset($data['class'], $data['method'], $data['arguments'])) {
                $ret = TaskDispatcher::execute($data);
            }

            if ($ret !== null) {
                $task->finish($ret);
            }

            EventEnum::ServerTaskEvent->emit([
                'server' => $server,
                'task' => $task,
                'data' => $data,
                'ret' => $ret,
            ]);

        } catch (Throwable $e) {
            Log::saveException($e, 'OnTaskEvent::handle');

            // 即使任务执行失败，也要完成任务以避免worker阻塞
            $task->finish([
                'error' => true,
                'message' => $e->getMessage(),
                'task_data' => $data
            ]);
        }
    }
}