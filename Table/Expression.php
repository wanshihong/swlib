<?php
declare(strict_types=1);

namespace Swlib\Table;

/**
 * 直接使用 mysql 语法的SQL 语句
 */
readonly class Expression
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
} 