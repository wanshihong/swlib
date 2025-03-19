<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Connect\MysqlHeart;
use Swlib\Connect\RedisHeart;
use Swlib\DataManager\WorkerManager;
use Swlib\Event\Event;
use Swoole\Server;

class OnWorkerStartEvent
{

    public Server $server;
    public int $workerId;

    public function handle(Server $server, int $workerId): void
    {
        $this->server = $server;
        $this->workerId = $workerId;

        WorkerManager::set("workerId", $workerId);
        WorkerManager::set("server", $server);
        echo ($server->taskworker ? "task" : '') . "WorkerStart:" . $workerId . ' pid:' . $server->getWorkerPid() . PHP_EOL;


        // 启动心跳
        MysqlHeart::start();
        RedisHeart::start();

        // 监听自定义事件
        Event::onMaps();

        // 这里比较特殊 先监听 才能抛出事件，不然无效
        Event::emit('OnWorkerStartEvent', [
            'workerId' => $workerId,
            'server' => $server,
        ]);

    }


}