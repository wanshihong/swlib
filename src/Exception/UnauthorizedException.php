<?php

namespace Swlib\Exception;

use Exception;
use Swlib\Utils\Language;
use Throwable;

/**
 * 用户登录状态验证失败
 */
class UnauthorizedException extends Exception
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