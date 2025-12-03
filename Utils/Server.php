<?php
declare(strict_types=1);

namespace Swlib\Utils;

use Swlib\DataManager\WorkerManager;
use Swlib\Exception\AppException;

class Server
{

    /**
     * 分发任务到 task 进程
     * 如果本身就是在 task 进程中,则直接执行
     *
     * @param array $runnable
     * @param array $data
     * @return void
     * @throws AppException
     */
    public static function task(array $runnable, array $data): void
    {
        /**@var \Swoole\Server $server */
        $server = WorkerManager::get('server');


        if ($server->taskworker) {
            $class = $runnable[0];
            $method = $runnable[1];
            new $class()->$method($data);
            return;
        }

        $server->task([
            'action' => $runnable,
            'data' => $data,
        ]);
    }
}