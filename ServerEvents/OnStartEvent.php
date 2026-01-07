<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Generate\ConfigEnum;
use Generate\RouterPath;
use ReflectionException;
use Swlib\App;
use Swlib\Event\EventEnum;
use Swlib\Parse\Helper\ConsoleColor;
use Swoole\Server;


class OnStartEvent
{
    public Server $server;

    /**
     * @throws ReflectionException
     */
    public function handle(Server $server): void
    {
        $this->server = $server;
        EventEnum::ServerStartEvent->emit([
            'server' => $server,
        ]);

        // 输出可访问的地址信息
        $useHttps = ConfigEnum::get('HTTPS');
        $port = ConfigEnum::PORT;
        $protocol = $useHttps ? 'https' : 'http';
        $localIP = App::getLocalIP();
        ConsoleColor::writeSuccessHighlight('✔ 服务器启动成功');

        $toolURL = "$protocol://$localIP:$port";

        echo "$protocol://127.0.0.1:$port" . PHP_EOL;
        echo $toolURL . PHP_EOL;


        echo "扩展 protobuf 字段工具: $toolURL/dev-tool/protobuf-ext-editor/index" . PHP_EOL;
        if (ConfigEnum::get('HTTPS')) {
            echo "IOS HTTPS 证书安装信任: $toolURL/dev-tool/dev-ssl-cert/ios" . PHP_EOL;
        }

        // 输出管理后台地址
        $dashboardPath = array_find(array_keys(RouterPath::PATHS), fn($path) => str_contains($path, 'dashboard/index'));
        if ($dashboardPath) {
            echo "管理后台地址: $toolURL$dashboardPath" . PHP_EOL;
        }

    }


}