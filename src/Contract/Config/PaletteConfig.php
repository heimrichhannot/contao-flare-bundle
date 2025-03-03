<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\DataContainer;

class PaletteConfig
{
    public function __construct(
        private readonly string        $alias,
        private readonly DataContainer $dataContainer,
        private string                 $prefix,
        private string                 $suffix
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getDataContainer(): DataContainer
    {
        return $this->dataContainer;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setSuffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }
}