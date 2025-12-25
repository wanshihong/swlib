<?php

namespace Swlib\Event\Abstract;


abstract class AbstractEvent
{
    abstract public function handle(array $args);
}
