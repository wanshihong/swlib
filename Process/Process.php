<?php

namespace Swlib\Process;

use Attribute;
use Generate\ProcessMap;

#[Attribute] class Process
{
    public function __construct(
        // 重定向子进程的标准输入和输出。【启用此选项后，在子进程内输出内容将不是打印屏幕，而是写入到主进程管道。读取键盘输入将变为从管道中读取数据。默认为阻塞读取。
        public bool $redirect_stdin_stdout = false,

        // unixSocket 类型【启用 $redirect_stdin_stdout 后，此选项将忽略用户参数，强制为 SOCK_STREAM。如果子进程内没有进程间通信，可以设置为 0】
        public int  $pipe_type = 0,

        // 启用协程，开启后可以直接在子进程的函数中使用协程 API
        public bool $enable_coroutine = true,

        // 进程启动后是常驻的，一次运行完以后间隔多久再次运行
        public int  $interval = 1
    )
    {

    }


    public static function run($server): void
    {
        foreach (ProcessMap::PROCESS as $p) {
            $server->addProcess(new \Swoole\Process(function (\Swoole\Process $process) use ($server, $p) {
                $className = $p['run'][0];
                $methodName = $p['run'][1];
                $ctrl = new $className();
                swoole_set_process_name('php_user_proc:' . $className);
                echo "start process: " . $className . "\n";
                while (true) {
                    $ctrl->$methodName($server, $process);
                    sleep($p['interval']);
                }
            }, $p['redirect_stdin_stdout'], $p['pipe_type'], $p['enable_coroutine']));
        }
    }


}