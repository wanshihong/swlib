<?php

namespace Swlib\Lock\Trait;

use RuntimeException;

trait LockAttributeTrait
{
    private function buildKey(array $ctx): string
    {
        $className = is_string($ctx['target']) ? $ctx['target'] : ($ctx['meta']['class'] ?? get_class($ctx['target']));

        if ($this->keyTemplate !== null && $this->keyTemplate !== '') {
            $params = $ctx['meta']['parameters'] ?? [];
            $index = array_search($this->keyTemplate, $params, true);
            if ($index === false || !array_key_exists($index, $ctx['arguments'])) {
                throw new RuntimeException("锁 keyTemplate '$this->keyTemplate' 未匹配到方法参数");
            }
            $value = $ctx['arguments'][$index];
            return $className . '::' . $ctx['meta']['method'] . ':' . $this->normalizeKeyValue($value);
        }

        return $className . '::' . $ctx['meta']['method'] . ':' . md5(serialize($ctx['arguments']));
    }

    private function normalizeKeyValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }

        return md5(serialize($value));
    }
}