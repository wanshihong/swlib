<?php

namespace Swlib\DataManager;


class DataManager
{
    public array $pool = [];
    
    public function get(string $key)
    {
        if (isset($this->pool[$key])) {
            return $this->pool[$key];
        }
        return null;
    }

    public function set(string $key, mixed $item): void
    {
        $this->pool[$key] = $item;
    }

    public function push(string $key, $item): void
    {
        if (empty($this->pool[$key])) {
            $this->pool[$key] = [];
        }
        $this->pool[$key][] = $item;
    }

    public function delete(string $key): void
    {
        unset($this->pool[$key]);
    }

    public function clear(): void
    {
        $this->pool = [];
    }

    public function getSet(string $key, callable $callback)
    {
        if ($ret = $this->get($key)) {
            return $ret;
        }
        $ret = $callback();
        if ($ret) {
            $this->set($key, $ret);
        }
        return $ret;
    }
}