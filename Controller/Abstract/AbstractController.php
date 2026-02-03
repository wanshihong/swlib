<?php
declare(strict_types=1);

namespace Swlib\Controller\Abstract;

use Swlib\Controller\Language\Enum\LanguageEnum;
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
            default => throw new AppException($name . LanguageEnum::NOT_FOUND),
        };
    }

    /**
     * @throws AppException
     */
    protected function get(string $key, $errTip = '', $def = null)
    {
        return \Swlib\Request\Request::get($key, $errTip, $def);
    }

    /**
     * @throws AppException
     */
    protected function post(string $key, $errTip = '', $def = null)
    {
        return \Swlib\Request\Request::post($key, $errTip, $def);
    }


    protected function getHeader(string $key, $def = null)
    {
        return \Swlib\Request\Request::getHeader($key, $def);
    }

}