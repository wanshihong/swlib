<?php

namespace Swlib\Process;

use Swoole\Process;
use Swoole\WebSocket\Server as WSServer;
use Swoole\Http\Server as HttpServer;

abstract class AbstractProcess
{

    abstract public function handle(WSServer|HttpServer $server, Process $process);

}