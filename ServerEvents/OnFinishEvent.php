<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Event\EventEnum;
use Swoole\Server;

class OnFinishEvent
{

    public Server $server;
    public int $task_id;
    public mixed $data;

    public function handle(Server $server, int $task_id, mixed $data): void
    {
        $this->server = $server;
        $this->task_id = $task_id;
        $this->data = $data;

        EventEnum::ServerFinishEvent->emit( [
            'server' => $server,
            'task_id' => $task_id,
            'data' => $data,
        ]);

    }
}