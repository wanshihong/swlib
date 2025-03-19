<?php
declare(strict_types=1);

namespace Swlib\Utils;

use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;

class Server
{

    /**
     * 分发任务到 task 进程
     *
     * 请不要在 task 进程中调用本方法，task 进程中支持添加到他task
     * 请不要在 task 进程中调用本方法，task 进程中支持添加到他task
     * 请不要在 task 进程中调用本方法，task 进程中支持添加到他task
     *
     * @param array $runnable
     * @param array $data
     * @return void
     * @throws AppException
     */
    public static function task(array $runnable, array $data): void
    {
        /**@var \Swoole\Server $server */
        $server = CtxEnum::Server->get();
        if (empty($server)) {
            if (ConfigEnum::APP_DEV === APP_ENV_DEV) {
                echo "协程上下文中没有 server ,请检查运行环境 ";
                echo "文件: " . __FILE__ . ", 行号: " . __LINE__ . PHP_EOL;
                var_dump($runnable);
                var_dump($data);
            }

            throw new AppException("协程上下文中没有 server ,请检查运行环境");
        }

        if ($server->taskworker) {
            throw new AppException("不要在 task 进程中调用本方法，task 进程中支持添加到他task");
        }

        $server->task([
            'action' => $runnable,
            'data' => $data,
        ]);
    }
}