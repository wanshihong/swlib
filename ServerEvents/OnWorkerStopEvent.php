<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Generate\DatabaseConnect;
use Swlib\Connect\MysqlHeart;
use Swlib\Connect\PoolRedis;
use Swlib\Connect\RedisHeart;
use Swlib\DataManager\WorkerManager;
use Swlib\Event\Attribute\Event;
use Swlib\Event\EventEnum;
use Swoole\Server;


class OnWorkerStopEvent
{
    public Server $server;
    public int $workerId;

    public function handle(Server $server, int $workerId): void
    {
        $this->server = $server;
        $this->workerId = $workerId;
        echo "OnWorkerStop:$workerId \n";


        EventEnum::ServerWorkerStopEvent->emit([
            'workerId' => $workerId,
            'server' => $server,
        ]);


        MysqlHeart::stop();
        RedisHeart::stop();
        PoolRedis::close();
        DatabaseConnect::close();


        WorkerManager::clear();

        // 移除监听自定义事件
        Event::offMaps();
    }


}