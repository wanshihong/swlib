<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Event\Event;
use Swoole\Server;

class OnPipeMessageEvent
{
    public Server $server;
    public int $src_worker_id;
    public mixed $data;

    public function handle(Server $server, int $src_worker_id, mixed $data): void
    {

        $this->server = $server;
        $this->src_worker_id = $src_worker_id;
        $this->data = $data;

        Event::emit('OnPipeMessageEvent', [
            'server' => $server,
            'src_worker_id' => $src_worker_id,
            'data' => $data,
        ]);
    }
}