<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Connect\MysqlHeart;
use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Connect\RedisHeart;
use Swlib\DataManager\WorkerManager;
use Swlib\Event\Event;
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


        Event::emit('OnWorkerStopEvent', [
            'workerId' => $workerId,
            'server' => $server,
        ]);

        MysqlHeart::stop();
        RedisHeart::stop();
        PoolRedis::close();
        PoolMysql::close();


        WorkerManager::clear();

        // 移除监听自定义事件
        Event::offMaps();
    }


}