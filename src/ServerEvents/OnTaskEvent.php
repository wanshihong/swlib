<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;


use Swlib\Event\Event;
use Swoole\Server;
use Swoole\Server\Task;


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
        if ($data['action']) {
            $class = $data['action'][0];
            $method = $data['action'][1];
            $ret = (new $class)->$method($data['data']);
        }

        if ($ret !== null) {
            $task->finish($ret);
        }

        Event::emit('OnTaskEvent', [
            'server' => $server,
            'task' => $task,
            'data' => $data,
            'ret' => $ret,
        ]);

    }

}