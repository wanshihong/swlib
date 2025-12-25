<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use InvalidArgumentException;
use Random\RandomException;
use Swlib\DataManager\FdManager;
use Swlib\DataManager\WorkerManager;
use Swlib\Enum\CtxEnum;
use Swlib\Event\EventEnum;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Response\ProtobufResponse;
use Swlib\Router\Router;
use Swlib\Utils\Cookie;
use Swlib\Utils\Ip;
use Swlib\Utils\Log;
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

        // 只移除前导 '/'，保留结尾 '/'，避免合法的空值 PathInfo 段被丢失导致奇数段错误
        // 注意：这里不能改回 trim($request->server['request_uri'], '/')，否则会重现本次 422 PathInfo 问题
        $uri = ltrim($request->server['request_uri'], '/');
        $isProtobufRequest = $this->isProtobufRequest($request);

        // 验证访问权限
        $permission = $this->_checkPermission($request,$response, $isProtobufRequest);
        if ($permission === false) {
            return;
        }

        // 解析路由配置 + 基础 URI + PathInfo 参数
        try {
            [$routeConfig, $baseUri, $pathInfo] = Router::parse($uri);
        } catch (InvalidArgumentException $e) {
            // PathInfo 结构非法，返回 422
            $response->status(422);
            $response->end('Unprocessable PathInfo:' . $e->getMessage());
            return;
        }

        if (empty($routeConfig)) {
            $response->status(404);
            $response->end('page not found');
            return;
        }

        // 合并 PathInfo 参数到 $request->get 中；若与 Query 中 key 冲突则返回 422
        $query = $request->get ?? [];
        $pathInfo = $pathInfo ?? [];
        if (!empty($pathInfo)) {
            $conflictKeys = array_intersect(array_keys($query), array_keys($pathInfo));
            if (!empty($conflictKeys)) {
                $response->status(422);
                $response->end('PathInfo and query parameters conflict');
                return;
            }

            $request->get = $query + $pathInfo;
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
                'uri' => $baseUri,
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
     * @return bool
     */
    private function _checkPermission(Request $request, Response $response, $isProtobufRequest): bool
    {
        if ($isProtobufRequest) {
            if (Router::checkSign($request) === false) {
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
        $cookieLang = Cookie::get('lang');
        $lang = $request->header['lang'] ?? ($cookieLang ?: $lang);
        $authorization = $request->header['authorization'] ?? '';

        // 与 handle() 保持一致：仅移除前导 '/'，不能移除结尾 '/'，否则 PathInfo 末尾空值段会在解析前被裁掉
        // 开发者请勿将此处改为 trim($request->server['request_uri'], '/')
        $uri = ltrim($request->server['request_uri'], '/');

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