<?php

namespace Swlib\Lock\Trait;

use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Exception\AppException;

trait LockAttributeTrait
{
    /**
     * @throws AppException
     */
    private function buildKey(array $ctx): string
    {
        $className = is_string($ctx['target']) ? $ctx['target'] : ($ctx['meta']['class'] ?? get_class($ctx['target']));

        if ($this->keyTemplate !== null && $this->keyTemplate !== '') {
            $params = $ctx['meta']['parameters'] ?? [];
            $index = array_search($this->keyTemplate, $params, true);
            if ($index === false || !array_key_exists($index, $ctx['arguments'])) {
                // 锁keyTemplate未匹配到方法参数
                throw new AppException(LanguageEnum::PARAM_INVALID . ": $this->keyTemplate");
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