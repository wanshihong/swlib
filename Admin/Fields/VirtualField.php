<?php

namespace Swlib\Admin\Fields;

use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Controller\Language\Service\Language;

class VirtualField extends AbstractField
{
    public string $templateList = "fields/lists/text.twig";
    public string $templateForm = "fields/form/text.twig";

    public mixed $batchValueResolver = null;
    public string $batchKey = '';
    public mixed $batchLoader = null;
    public mixed $batchRowValueResolver = null;

    public function __construct(string $field, string $label)
    {
        $this->field = $field;
        $this->label = Language::get($label);
        $this->dbName = 'default';
        $this->elemId = str_replace(['.', '_'], '-', $field);
        $this->fieldVar = $field;
        $this->default = '';

        $this->onlyOnList();
        $this->batchKey = $field;
    }

    public function frameworkShouldQueryField(): bool
    {
        return false;
    }

    public function frameworkIsVirtualField(): bool
    {
        return true;
    }

    public function setBatchValueResolver(callable $resolver): static
    {
        $this->batchValueResolver = $resolver;
        return $this;
    }

    public function setBatchKey(string $key): static
    {
        $this->batchKey = $key;
        return $this;
    }

    public function setBatchLoader(callable $loader): static
    {
        $this->batchLoader = $loader;
        return $this;
    }

    public function setBatchRowValueResolver(callable $resolver): static
    {
        $this->batchRowValueResolver = $resolver;
        return $this;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int|string,mixed>
     */
    public function resolveBatchValues(array $rows, AbstractAdmin $admin): array
    {
        if ($this->batchValueResolver === null) {
            return [];
        }

        return (array)call_user_func($this->batchValueResolver, $rows, $admin);
    }
}
