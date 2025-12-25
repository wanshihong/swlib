<?php

namespace Swlib\Process\Abstract;

use Swoole\Http\Server as HttpServer;
use Swoole\Process;
use Swoole\WebSocket\Server as WSServer;

abstract class AbstractProcess
{

    abstract public function handle(WSServer|HttpServer $server, Process $process);

}