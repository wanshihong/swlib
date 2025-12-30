<?php

namespace Swlib\Process;

use Generate\ProcessMap;
use Swlib\Parse\Helper\ConsoleColor;
use Swoole\Coroutine\System;

class Process
{


    public static function run($server): void
    {
        foreach (ProcessMap::PROCESS as $p) {
            $server->addProcess(new \Swoole\Process(function (\Swoole\Process $process) use ($server, $p) {
                $className = $p['run'][0];
                $methodName = $p['run'][1];
                $ctrl = new $className();
                swoole_set_process_name('php_user_proc:' . $className);
                ConsoleColor::write("start process: $className->$methodName()");
                while (true) {
                    $ctrl->$methodName($server, $process);
                    if (($p['interval'] ?? 0) > 0) {
                        // ProcessAttribute 中约定 interval 单位为毫秒，这里统一转换为秒
                        $sleepSeconds = $p['interval'] / 1000;
                        System::sleep($sleepSeconds);
                    }
                }
            }, $p['redirect_stdin_stdout'] ?? false, $p['pipe_type'] ?? 0, $p['enable_coroutine'] ?? true));
        }
    }


}