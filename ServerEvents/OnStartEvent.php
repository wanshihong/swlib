<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Generate\ConfigEnum;
use ReflectionClass;
use Swlib\Event\EventEnum;
use Swlib\Utils\File;
use Swoole\Server;


class OnStartEvent
{
    public Server $server;

    public function handle(Server $server): void
    {
        $this->server = $server;
        EventEnum::ServerStartEvent->emit([
            'server' => $server,
        ]);
        // 记录 masterPid
        File::save(RUNTIME_DIR . 'server_pid.txt', $server->master_pid);

        // 输出可访问的地址信息
        $reflection = new ReflectionClass(ConfigEnum::class);
        $useHttps = $reflection->hasConstant('HTTPS') && $reflection->getConstant('HTTPS');
        $port = ConfigEnum::PORT;
        $protocol = $useHttps ? 'https' : 'http';
        $localIP = $this->getLocalIP();
        echo "服务器启动成功！" . PHP_EOL;
        echo "访问地址:" . PHP_EOL;
        echo "$protocol://127.0.0.1:$port" . PHP_EOL;
        echo "$protocol://$localIP:$port" . PHP_EOL;

    }

    private function getLocalIP(): string
    {
        // 创建一个UDP socket
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        // 连接到一个外部地址（这里用的是Google的公共DNS）
        @socket_connect($socket, '8.8.8.8', 80);
        // 获取socket连接的本机地址
        socket_getsockname($socket, $addr);
        socket_close($socket);
        return $addr;
    }

}