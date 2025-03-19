<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swoole\Server;


class OnReceive
{
    public function handle(Server $server, int $fd, int $reactorId, string $data): void
    {

    }

}