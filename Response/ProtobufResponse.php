<?php

namespace Swlib\Response;

use Generate\ConfigEnum;
use Google\Protobuf\Internal\Message;
use Swlib\DataManager\WorkerManager;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Exception\TokenExpiredException;
use Swlib\Exception\UnauthorizedException;
use Swlib\Utils\Log;
use Protobuf\Common\Response;
use Swoole\WebSocket\Server;
use Throwable;

class ProtobufResponse implements ResponseInterface
{

    private ?Server $server = null;
    private ?int $fd = null;

    public function setWebSocketServer(Server $server, int $fd): static
    {
        $this->server = $server;
        $this->fd = $fd;
        return $this;
    }


    public function __construct(private readonly Response $responseMessage, private readonly int $statusCode = 200)
    {
    }

    /**
     * 返回正确
     * @param Message $message
     * @return static
     */
    public static function success(Message $message): static
    {
        $responseMessage = new Response();
        $responseMessage->setErrno(0);
        $responseMessage->setPath(self::getUri());
        $responseMessage->setData($message->serializeToString());
        return new static($responseMessage);
    }

    private static function getUri()
    {
        $uri = CtxEnum::URI->get('');
        if (str_starts_with($uri, '/')) {
            $uri = substr($uri, 1);
        }
        return $uri;
    }

    /**
     * 返回错误
     * @param Throwable $e
     * @return static
     */
    public static function error(Throwable $e): static
    {
        $ret = "server error";
        if (ConfigEnum::APP_PROD === false) {
            $ret = Log::getTraceMsg($e);
        } else {
            // 生产环境下，AppException、UnauthorizedException 和 TokenExpiredException 都返回具体消息
            if ($e instanceof AppException || $e instanceof UnauthorizedException || $e instanceof TokenExpiredException) {
                $ret = $e->getMessage();
            }
        }
        Log::saveException($e, 'request');

        /** @var \Swoole\Http\Response $response */
        $response = CtxEnum::Response->get();
        $responseMessage = new Response();
        if ($response) {
            // http 请求 ，一定会会有  response
            $responseMessage->setErrno(1);
            $responseMessage->setPath(self::getUri());
            $responseMessage->setMsg($ret);

            // Token 过期，需要刷新（返回 419）
            if ($e instanceof TokenExpiredException) {
                return new static($responseMessage, 419);
            }

            // 未登录或登录失效（返回 401）
            if ($e instanceof UnauthorizedException) {
                return new static($responseMessage, 401);
            }
        } else {
            // websocket 请求
            // websocket 通过 错误码 通知前台
            if ($e instanceof TokenExpiredException) {
                // Token 过期，需要刷新
                $responseMessage->setErrno(419);
                $responseMessage->setMsg('token-expired-please-refresh');
                return new static($responseMessage, 419);
            }
            if ($e instanceof UnauthorizedException) {
                // 未登录，需要登录
                $responseMessage->setErrno(401);
                $responseMessage->setMsg('please-login');
                return new static($responseMessage, 401);
            }
        }


        return new static($responseMessage);
    }


    /**
     * @throws AppException
     */
    public function output(): void
    {
        $retStr = $this->responseMessage->serializeToString();

        /** @var \Swoole\Http\Response $response */
        $response = CtxEnum::Response->get();
        if ($response) {
            // http 请求 ，一定会会有  response
            $response->header('Content-Type', 'application/x-protobuf; charset=utf-8');
            $response->status($this->statusCode);
            $response->end($retStr);
        } else {
            // websocket 请求
            $server = $this->server;
            if (empty($server)) {
                $server = CtxEnum::Server->get();
            }
            if (empty($server)) {
                $server = WorkerManager::get("server");
            }

            if (empty($server)) {
                throw new AppException("server is null");
            }

            $fd = $this->fd ?: CtxEnum::Fd->get();
            if ($server->exists($fd)) {
                $server->push($fd, $retStr, SWOOLE_WEBSOCKET_OPCODE_BINARY);
            }
        }

    }

}