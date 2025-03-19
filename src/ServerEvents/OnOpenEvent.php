<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\DataManager\FdManager;
use Swlib\Enum\CtxEnum;
use Swlib\Event\Event;
use Swoole\Http\Request;
use Swoole\WebSocket\Server;

class OnOpenEvent
{
    public Server $server;
    public Request $request;

    public function handle(Server $server, Request $request): void
    {
        $this->server = $server;
        $this->request = $request;
        CtxEnum::Server->set($server);
        CtxEnum::Request->set($request);
        FdManager::new($request->fd)->set(CtxEnum::Lang->value, $request->header['lang'] ?? 'zh');
        $fd = $request->fd;
        $workerId = $server->getWorkerId();
        $workerPId = $server->getWorkerPid();

        Event::emit('OnOpenEvent', [
            'server' => $server,
            'request' => $request,
            'fd' => $fd,
            'workerId' => $workerId,
            'workerPId' => $workerPId,
        ]);


    }
}