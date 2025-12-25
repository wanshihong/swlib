<?php

namespace Swlib\Admin\Config;


use Swlib\Admin\Controller\Interface\AdminControllerInterface;
use Swlib\Admin\Fields\AbstractField;
use Swlib\Admin\Trait\PageFrameworkTrait;


class PageFieldsConfig
{
    use PageFrameworkTrait;

    /**
     * @var AbstractField[] array
     */
    public array $fields = [];

    public function __construct(
        public AdminControllerInterface $admin
    )
    {
    }

    public function setFields(AbstractField ...$fields): self
    {
        $this->fields = $fields;
        return $this;
    }


}