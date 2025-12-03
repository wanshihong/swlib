<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Generate\ConfigEnum;
use Swlib\DataManager\FdManager;
use Swlib\Enum\CtxEnum;
use Swlib\Event\EventEnum;
use Swoole\Http\Request;
use Swoole\WebSocket\Server;

class OnOpenEvent
{
    public Server $server;
    public Request $request;

    public function handle(Server $server, Request $request): void
    {
        $fd = $request->fd;
        $workerId = $server->getWorkerId();
        $workerPId = $server->getWorkerPid();
        $this->server = $server;
        $this->request = $request;
        CtxEnum::Server->set($server);
        CtxEnum::Request->set($request);
        FdManager::new($fd)->set('server', $server);
        FdManager::new($fd)->set('request', $request);

        $random = $this->_getParams($request, "random", $fd);
        $time = $this->_getParams($request, "time", $fd);
        $token = $this->_getParams($request, "token", $fd);
        // 接收 authorization token
        $authorization = $this->_getParams($request, "authorization", $fd);

        $myToken = md5($authorization . '.' . $random . '.' . $time);
        if ($token !== $myToken) {
            $server->disconnect($fd, SWOOLE_WEBSOCKET_CLOSE_NORMAL, 'token error');
            return;
        }
        // 接收appid
        $appid = $this->_getParams($request, "appid", $fd);
        // 接收 语言
        $lang = $this->_getParams($request, "lang", $fd);
        if ($lang) {
            CtxEnum::Lang->set($lang);
        }

        EventEnum::WebSocketOnOpenEvent->emit([
            'fd' => $fd, // 连接标识
            'lang' => $lang, // 语言
            'appid' => $appid, // 应用ID
            'server' => $server, // 服务
            'request' => $request, // 请求
            'workerId' => $workerId, // 工作进程ID
            'workerPId' => $workerPId, // 工作进程PID
            'authorization' => $authorization, // token
        ]);


        if (ConfigEnum::APP_PROD === false) {
            echo "connection open: $fd\n";
        }
    }

    private function _getParams($request, string $key, int $fd)
    {
        $value = $request->get[$key] ?? null;
        if ($value === null) {
            $value = $request->header[$key] ?? null;
        }
        if ($value) {
            CtxEnum::Data->setData($key, $value);
            FdManager::new($fd)->set($key, $value);
            return $value;
        }
        return null;
    }
}