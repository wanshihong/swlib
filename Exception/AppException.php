<?php

namespace Swlib\Exception;

use Exception;
use Swlib\Controller\Language\Service\Language;
use Throwable;

class AppException extends Exception
{
    /**
     * @param string $message
     * @param mixed $arg
     * @throws Throwable
     */
    public function __construct(string $message = "", ...$arg)
    {
        $message = Language::get($message, ...$arg);
        parent::__construct($message);
    }
}