<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\DataManager\FdManager;
use Swlib\DataManager\WorkerManager;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Log;
use Swlib\Event\EventEnum;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Response\ProtobufResponse;
use Swlib\Router\Router;
use Swlib\Utils\Func;
use Swlib\Utils\Ip;
use Random\RandomException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use Throwable;

class OnRequestEvent
{
    private const array CORS_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Headers' => '*',
        'Access-Control-Expose-Headers' => 'random,time,token,sign-type,sign,lang,url,request-id,app-id,authorization,x-forwarded-proto,scheme,invite-code',
    ];
    public Request $request;
    public Response $response;

    public function handle(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;

        // 从 WorkerManager 获取 server 实例
        $server = WorkerManager::get('server');
        if (!$server) {
            Log::error('Server instance not found in WorkerManager', [], 'server_error');
            $response->status(500);
            $response->end('Internal server error');
            return;
        }

        EventEnum::HttpRequestEvent->emit([
            'request' => $request,
            'response' => $response,
            'server' => $server,
        ]);
        $this->setCorsHeaders($response);

        if ($request->getMethod() === 'OPTIONS') {
            $response->end();
            return;
        }

        if ($this->isFaviconRequest($request)) {
            $response->end();
            return;
        }

        $uri = trim($request->server['request_uri'], '/');
        $isProtobufRequest = $this->isProtobufRequest($request);

        // 验证访问权限
        $permission = $this->_checkPermission($request, $response, $isProtobufRequest, $uri);
        if ($permission === false) {
            return;
        }

        // 获取路由配置
        $routeConfig = Router::get($uri);
        if (empty($routeConfig)) {
            $response->status(404);
            $response->end('page not found');
            return;
        }

        $method = $request->getMethod();
        if (!in_array($method, ($routeConfig['method'] ?? []))) {
            $response->status(405);
            $response->end('Access not allowed:' . $method);
            return;
        }


        try {
            // 保存协程参数，后续可以直接使用
            $this->_saveCtx($request, $response, $server);


            // 触发进入路由事件
            EventEnum::HttpRouteEnterEvent->emit([
                'uri' => $uri,
                'ip' => Ip::get(),
                'server' => $server,
                'request' => $request,
            ]);


            $router = new Router();
            $router->run($request->rawContent(), $routeConfig);

            // 清空连接数据
            FdManager::new($request->fd)->clear();
        } catch (Throwable $e) {
            Log::saveException($e, 'onRequest');
            // 路由执行捕获到异常， 返回错误提示到前台
            if ($isProtobufRequest) {
                try {
                    ProtobufResponse::error($e)->output();
                } catch (AppException $e) {
                    JsonResponse::error($e)->output();
                }
            } else {
                JsonResponse::error($e)->output();
            }
        }

    }


    private function setCorsHeaders(Response $response): void
    {
        foreach (self::CORS_HEADERS as $key => $value) {
            $response->setHeader($key, $value);
        }
    }

    private function isFaviconRequest(Request $request): bool
    {
        return $request->server['path_info'] === '/favicon.ico' || $request->server['request_uri'] === '/favicon.ico';
    }

    private function isProtobufRequest(Request $request): bool
    {
        return isset($request->header['content-type']) && stripos($request->header['content-type'], 'protobuf') !== false;
    }

    /**
     * 验证访问权限
     * @param Request $request
     * @param Response $response
     * @param $isProtobufRequest
     * @param string $uri
     * @return bool
     */
    private function _checkPermission(Request $request, Response $response, $isProtobufRequest, string $uri): bool
    {
        if ($isProtobufRequest) {
            $random = $request->header['random'] ?? '';
            $token = $request->header['token'] ?? '';
            $time = $request->header['time'] ?? '';
            if (empty($random) || empty($token) || empty($time)) {
                $response->end('Refuse to process the request');
                return false;
            }

            if (Router::checkSign($random, $token, (int)$time, $uri) === false) {
                $response->end('Refuse to process the request!');
                return false;
            }
        }
        return true;
    }

    /**
     * 保存协程参数，后续可以直接使用
     * @throws Throwable
     */
    private function _saveCtx(Request $request, Response $response, Server $server): void
    {
        $workerId = WorkerManager::get('workerId');
        $requestId = $this->generateRequestId();

        $lang = 'zh';
        $langCookieKey = Func::getCookieKey('lang');
        if (isset($request->header['lang'])) {
            $lang = $request->header['lang'];
        } elseif (isset($request->cookie[$langCookieKey])) {
            $lang = $request->cookie[$langCookieKey];
        }
        $authorization = $request->header['authorization'] ?? '';
        $uri = trim($request->server['request_uri'], '/');

        CtxEnum::URI->set($uri);
        CtxEnum::WorkerId->set($workerId);
        CtxEnum::Request->set($request);
        CtxEnum::Response->set($response);
        CtxEnum::Server->set($server);
        CtxEnum::RequestId->set($requestId);
        CtxEnum::Lang->set($lang);
        CtxEnum::Data->setData('authorization', $authorization);
        $response->setHeader('request-id', $requestId);
    }


    /**
     * @throws RandomException
     */
    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16)); // 生成32位长度的随机字符串作为requestId
    }


}