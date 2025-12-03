<?php

namespace Swlib\Response;

readonly class EmptyResponse implements ResponseInterface
{


    public static function new(): static
    {
        return new static();
    }

    public function output()
    {
    }

}