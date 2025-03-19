<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Exception;
use Swlib\DataManager\FdManager;
use Swlib\Event\Event;
use Swlib\Utils\Log;
use Swoole\WebSocket\Server;

class OnCloseEvent
{
    public Server $server;


    public function handle(Server $server, int $fd): void
    {
        $this->server = $server;
        Event::emit('OnCloseEvent', [
            'server' => $server,
            'fd' => $fd,
        ]);
        try {
            // 清空连接数据
            FdManager::new($fd)->clear();
        } catch (Exception $e) {
            Log::saveException($e, 'onClose');
        }
    }

}