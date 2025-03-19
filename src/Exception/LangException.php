<?php

namespace Swlib\Exception;

use Exception;

class LangException extends Exception
{
    public function __construct(string $message = "")
    {
        parent::__construct($message);
    }
}