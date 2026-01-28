<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Exception;
use Swlib\DataManager\FdManager;
use Swlib\DataManager\WorkerManager;
use Swlib\Enum\CtxEnum;
use Swlib\Event\EventEnum;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Response\ProtobufResponse;
use Swlib\Router\Router;
use Swlib\Utils\Ip;
use Swlib\Utils\Log;
use Protobuf\Common\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Throwable;

class OnMessageEvent
{
    public Server $server;
    public Frame $frame;

    /**
     * @throws Exception
     */
    public function handle(Server $server, Frame $frame): void
    {
        $this->server = $server;
        $this->frame = $frame;

        $binaryString = substr($frame->data, 0); // PHP的substr用于将整个字符串视为二进制数据
        $isProtobufRequest = false;
        try {
            // 保存协程参数，后续可以直接使用
            $this->_saveCtx($server, $frame->fd);

            try {
                $protoRequest = new Request();
                $protoRequest->mergeFromString($binaryString);
                $uri = $protoRequest->getUri();
                $data = $protoRequest->getData();
                $isProtobufRequest = true;
            } catch (Exception) {
                $jsonRequest = json_decode($frame->data, true);
                if (empty($jsonRequest)) {
                    throw new AppException(AppErr::WS_PAGE_NOT_FOUND);
                }
                $uri = $jsonRequest['uri'];
                $data = $jsonRequest['data'];
            }

            CtxEnum::URI->set($uri);

            // 触发WebSocketOnMessageEvent事件
            // 放在 +ping 前面;可能部分应用有定时心跳的需求;
            // 前台发送+ping 的时候,可以做一些维持连接的事情
            // 一些必要的参数 可以在 onOpen 事件中传递
            EventEnum::WebSocketOnMessageEvent->emit([
                'server' => $server,
                'frame' => $frame,
                'fd' => $frame->fd,
            ]);

            if ($uri == '+ping') {
                $this->server->push($frame->fd, 'pong');
                return;
            }


            // 触发进入路由事件
            EventEnum::HttpRouteEnterEvent->emit([
                'uri' => $uri,
                'ip' => Ip::get(),
                'server' => $this->server,
                'request' => CtxEnum::Request->get(),
            ]);


            // 获取路由配置
            $routeConfig = Router::get($uri);
            if (empty($routeConfig)) {
                throw new AppException(AppErr::WS_PAGE_NOT_FOUND);
            }

            // 检查请求类型
            if (!in_array('WS', $routeConfig['method']) && !in_array('WSS', $routeConfig['method'])) {
                throw new AppException(AppErr::WS_ACCESS_NOT_ALLOWED);
            }


            $router = new Router();
            $router->run($data, $routeConfig);
        } catch (Throwable $e) {
            Log::saveException($e, 'onMessage');
            // 路由执行捕获到异常， 返回错误提示到前台
            if ($isProtobufRequest) {
                ProtobufResponse::error($e)->output();
            } else {
                JsonResponse::error($e)->output();
            }
        }
    }

    /**
     * 保存协程参数，后续可以直接使用
     * @throws Throwable
     */
    private function _saveCtx(Server $server, int $fd): void
    {
        $workerId = WorkerManager::get('workerId');
        $lang = FdManager::new($fd)->get(CtxEnum::Lang->value);
        CtxEnum::Lang->set($lang);
        CtxEnum::Fd->set($fd);
        CtxEnum::WorkerId->set($workerId);
        CtxEnum::Server->set($server);
        CtxEnum::Lang->set($lang);

        $request = FdManager::new($fd)->get('request');
        CtxEnum::Request->set($request);

        $this->_setCtx($fd, 'appid');
        $this->_setCtx($fd, 'request');
        $this->_setCtx($fd, 'authorization');
    }

    private function _setCtx(int $fd, string $key): void
    {
        $value = FdManager::new($fd)->get($key);
        if ($value) {
            CtxEnum::Data->setData($key, $value);
        }
    }


}