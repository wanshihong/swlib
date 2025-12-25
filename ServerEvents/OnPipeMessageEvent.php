<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Event\EventEnum;
use Swlib\Utils\Log;
use Swoole\Server;
use Throwable;

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

        EventEnum::ServerPipeMessageEvent->emit([
            'server' => $server,
            'src_worker_id' => $src_worker_id,
            'data' => $data,
        ]);


        if (isset($data['runnable'])) {
            try {
                $ctrl = $data['runnable'][0];
                $method = $data['runnable'][1];
                new $ctrl()->$method($data);
            } catch (Throwable $e) {
                Log::saveException($e, 'onPipeMessage');
            }
        }
    }
}