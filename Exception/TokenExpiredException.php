<?php

namespace Swlib\Exception;

use Exception;
use Swlib\Controller\Language\Service\Language;
use Throwable;

/**
 * JWT Token 过期异常
 * 用于短 Token 过期但长 Token 仍然有效的情况
 * 前端收到 419 状态码后会自动刷新 Token 并重试请求
 */
class TokenExpiredException extends Exception
{
    /**
     * @throws Throwable
     */
    public function __construct(string $message = "", ...$arg)
    {
        $message = Language::get($message, ...$arg);
        parent::__construct($message);
    }
}

