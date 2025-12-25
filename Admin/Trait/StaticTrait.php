<?php

namespace Swlib\Admin\Trait;

trait StaticTrait
{

    public array $cssFiles = [];
    public array $jsFiles = [];

    public function addCssFile(string $cssFile): static
    {
        if (!in_array($cssFile, $this->cssFiles)) {
            $this->cssFiles[] = $cssFile;
        }

        return $this;
    }

    public function addJsFile(string $jsFile): static
    {
        if (!in_array($jsFile, $this->jsFiles)) {
            $this->jsFiles[] = $jsFile;
        }
        return $this;
    }


}