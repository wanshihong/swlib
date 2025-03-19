<?php

namespace Swlib\Event;


abstract class AbstractEvent
{
    abstract public function handle(array $args);
}
