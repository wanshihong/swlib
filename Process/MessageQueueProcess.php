<?php
declare(strict_types=1);

namespace Swlib\Process;

use Error;
use Swlib\Process\Abstract\AbstractProcess;
use Swlib\Process\Attribute\ProcessAttribute;
use Swlib\Queue\MessageQueue;
use Swlib\Utils\Log;
use Swoole\WebSocket\Server;
use Throwable;

#[ProcessAttribute(interval: 10 * 1000)]
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