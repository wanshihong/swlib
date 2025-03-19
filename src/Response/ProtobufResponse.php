<?php

namespace Swlib\Response;

use Generate\ConfigEnum;
use Google\Protobuf\Internal\Message;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Exception\UnauthorizedException;
use Swlib\Utils\Log;
use Protobuf\Common\Response;
use Throwable;

readonly class ProtobufResponse implements ResponseInterface
{


    public function __construct(private Response $responseMessage, private int $statusCode = 200)
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
        $responseMessage->setData($message->serializeToString());
        return new static($responseMessage);
    }


    /**
     * 返回错误
     * @param Throwable $e
     * @return static
     */
    public static function error(Throwable $e): static
    {
        $ret = "server error";
        if (ConfigEnum::APP_DEV === APP_ENV_DEV) {
            $ret = Log::getTraceMsg($e);
        } else {
            if ($e instanceof AppException) {
                $ret = $e->getMessage();
            }
        }
        Log::saveException($e, 'request');

        $responseMessage = new Response();
        $responseMessage->setErrno(1);
        $responseMessage->setMsg($ret);
        if ($e instanceof UnauthorizedException) {
            return new static($responseMessage, 401);
        }
        return new static($responseMessage);
    }


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
            $server = CtxEnum::Server->get();
            $fd = CtxEnum::Fd->get();
            $server->push($fd, $retStr, SWOOLE_WEBSOCKET_OPCODE_BINARY);
        }

    }

}