<?php

namespace Swlib\Response;

use Swlib\Enum\CtxEnum;
use Swoole\Http\Response;

readonly class RedirectResponse implements ResponseInterface
{


    public function __construct(private string $url, private int $code)
    {
    }

    public static function url($url, int $code = 302): static
    {
        return new static($url, $code);
    }

    public function output(): void
    {
        /** @var Response $response */
        $response = CtxEnum::Response->get();
        $response->redirect($this->url, $this->code);
    }

}