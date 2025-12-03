<?php

namespace Swlib\Exception;

use Exception;
use Swlib\Enum\CtxEnum;

class RedirectException extends Exception
{

    public function __construct(string $message, string $url, int $code = 302)
    {
        parent::__construct($message, $code);
        $response = CtxEnum::Response->get();
        $response->redirect($url, $code);
    }
}