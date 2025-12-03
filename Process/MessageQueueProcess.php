<?php
declare(strict_types=1);

namespace Swlib\Process;

use Error;
use Swlib\Queue\MessageQueue;
use Swlib\Utils\Log;
use Swoole\WebSocket\Server;
use Throwable;

#[Process(interval: 10)]
class MessageQueueProcess extends AbstractProcess
{
    public function handle(Server|\Swoole\Http\Server $server, \Swoole\Process $process): void
    {
        try {
            MessageQueue::run();
        } catch (Throwable|Error $e) {
            Log::saveException($e, 'queue');
        }
    }
}