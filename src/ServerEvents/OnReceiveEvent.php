<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Event\Event;
use Swoole\Server;


class OnReceiveEvent
{

    public Server $server;
    public int $fd;
    public int $reactorId;
    public string $data;

    public function handle(Server $server, int $fd, int $reactorId, string $data): void
    {
        $this->server = $server;
        $this->fd = $fd;
        $this->reactorId = $reactorId;
        $this->data = $data;
        Event::emit('OnReceiveEvent', [
            'server' => $server,
            'fd' => $fd,
            'reactorId' => $reactorId,
            'data' => $data,
        ]);
    }

}