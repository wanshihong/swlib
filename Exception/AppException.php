<?php

namespace Swlib\Exception;

use Exception;
use Swlib\Utils\Language;
use Throwable;

class AppException extends Exception
{
    /**
     * @param string $message
     * @param string $arg
     * @throws Throwable
     */
    public function __construct(string $message = "", ...$arg)
    {
        $message = Language::get($message, ...$arg);
        parent::__construct($message);
    }
}