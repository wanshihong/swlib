<?php

namespace Swlib\Response;

use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Log;
use Swlib\Utils\Twig;
use Throwable;

readonly class TwigResponse implements ResponseInterface
{


    public function __construct(private string $data)
    {
    }

    public static function render($template, array $data = []): static
    {
        try {
            $html = Twig::getInstance()->twig->render($template, $data);
        } catch (Throwable $e) {
            $html = 'server error';
            Log::saveException($e, 'admin');
            if (ConfigEnum::APP_DEV !== APP_ENV_PROD) {
                $html = $e->getMessage() . "\n" . $e->getTraceAsString();
            }
        }
        return new static($html);
    }

    public function output(): void
    {
        $response = CtxEnum::Response->get();
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->end($this->data);
    }

}