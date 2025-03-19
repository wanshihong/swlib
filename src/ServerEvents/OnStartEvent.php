<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Event\Event;
use Swoole\Server;


class OnStartEvent
{
    public Server $server;

    public function handle(Server $server): void
    {
        $this->server = $server;
        Event::emit('OnStartEvent', [
            'server' => $server,
        ]);
        // 记录 masterPid
        file_put_contents(RUNTIME_DIR . 'server_pid.txt', $server->master_pid);
    }

}