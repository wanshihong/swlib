<?php

namespace Swlib\Exception;

use Exception;
use Swlib\Controller\Language\Service\Language;

class AppException extends Exception
{
    /**
     * @param string $message 翻译 key
     * @param array<string, mixed> $params 参数数组，如 ['bizType' => 'withdraw']
     * @throws AppException
     */
    public function __construct(string $message = "", array $params = [])
    {
        $message = Language::get($message, $params);
        parent::__construct($message);
    }
}