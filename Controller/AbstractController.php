<?php
declare(strict_types=1);

namespace Swlib\Controller;

use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * @property Request $request
 * @property Response $response
 */
abstract class  AbstractController
{


    /**
     * @throws AppException
     */
    public function __get(string $name)
    {
        return match ($name) {
            CtxEnum::Request->value => CtxEnum::Request->get(),
            CtxEnum::Response->value => CtxEnum::Response->get(),
            default => throw new AppException("属性 %s 未定义", $name),
        };
    }

    /**
     * @throws AppException
     */
    protected function get(string $key, $errTip = '', $def = null)
    {
        $get = $this->request->get ?: [];
        if (isset($get[$key])) {
            return $get[$key];
        }

        if ($def === null) {
            throw new AppException($errTip ?: "参数错误");
        }
        return $def;
    }

    /**
     * @throws AppException
     */
    protected function post(string $key, $errTip = '', $def = null)
    {
        $post = $this->request->post;
        if (empty($post)) {
            $post = json_decode($this->request->getContent(), true);
        }
        $post = $post ?: [];
        if (isset($post[$key])) {
            return $post[$key];
        }

        if ($def === null) {
            throw new AppException($errTip ?: '参数错误');
        }
        return $def;
    }


    protected function getHeader(string $key, $def = null)
    {
        $header = $this->request->header ?: [];
        if (isset($header[$key])) {
            return $this->request->header[$key];
        }

        return $def;
    }

}