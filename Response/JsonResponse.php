<?php

namespace Swlib\Response;

use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Exception\TokenExpiredException;
use Swlib\Exception\UnauthorizedException;
use Swlib\Utils\Log;
use stdClass;
use Swoole\WebSocket\Server;
use Throwable;

readonly class JsonResponse implements ResponseInterface
{


    public function __construct(private string $data, private int $statusCode = 200)
    {
    }

    /**
     * 返回正确
     * @param mixed $data
     * @return static
     */
    public static function success(mixed $data = []): static
    {
        return new static(json_encode([
            'errno' => 0,
            'msg' => 'success',
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));
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
            if ($e instanceof AppException || $e instanceof UnauthorizedException || $e instanceof TokenExpiredException) {
                $ret = $e->getMessage();
            }
        }
        Log::saveException($e, 'request');

        // Token 过期，需要刷新（返回 419）
        if ($e instanceof TokenExpiredException) {
            return new static(json_encode([
                'errno' => 1,
                'msg' => $ret,
                'data' => new stdClass(),
            ], JSON_UNESCAPED_UNICODE), 419);
        }

        // 未登录或登录失效（返回 401）
        if ($e instanceof UnauthorizedException) {
            return new static(json_encode([
                'errno' => 1,
                'msg' => $ret,
                'data' => new stdClass(),
            ], JSON_UNESCAPED_UNICODE), 401);
        }

        return new static(json_encode([
            'errno' => 1,
            'msg' => $ret,
            'data' => new stdClass(),
        ], JSON_UNESCAPED_UNICODE));

    }

    public function output(): void
    {
        $response = CtxEnum::Response->get();
        if ($response) {
            // http 请求 ，一定会会有  response
            $response->header('Content-Type', 'application/json; charset=utf-8');
            $response->status($this->statusCode);
            $response->end($this->data);
        } else {
            // websocket 请求
            /** @var Server $server */
            $server = CtxEnum::Server->get();
            $fd = CtxEnum::Fd->get();
            $server->push($fd, $this->data);
        }
    }

}