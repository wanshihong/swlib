<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Exception;
use Swlib\DataManager\FdManager;
use Swlib\DataManager\WorkerManager;
use Swlib\Enum\CtxEnum;
use Swlib\Event\Event;
use Swlib\Response\JsonResponse;
use Swlib\Response\ProtobufResponse;
use Swlib\Router\Router;
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

        Event::emit('OnMessageEvent', [
            'server' => $server,
            'frame' => $frame,
        ]);

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
                    throw new Exception('page not found');
                }
                $uri = $jsonRequest['uri'];
                $data = $jsonRequest['data'];
            }


            // 获取路由配置
            $routeConfig = Router::get($uri);
            if (empty($routeConfig)) {
                throw new Exception('page not found');
            }

            // 检查请求类型
            if (!in_array('WS', $routeConfig['method']) && !in_array('WSS', $routeConfig['method'])) {
                throw new Exception('Access not allowed');
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
    }


}