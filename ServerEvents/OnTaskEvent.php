<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;


use Swlib\Event\EventEnum;
use Swlib\Utils\Log;
use Swoole\Server;
use Swoole\Server\Task;
use Throwable;


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
            if (isset($data['action']) && is_array($data['action'])) {
                $class = $data['action'][0];
                $method = $data['action'][1];

                if (class_exists($class) && method_exists($class, $method)) {
                    $ret = (new $class)->$method($data['data']);
                } else {
                    Log::save("Task action class '$class' or method '$method' not found");
                }
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