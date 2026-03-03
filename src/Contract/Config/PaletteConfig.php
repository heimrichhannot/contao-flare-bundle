<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class PaletteConfig
{
    public function __construct(
        private readonly string        $type,
        private readonly DataContainer $dataContainer,
        private string                 $prefix,
        private string                 $suffix,
        private readonly ListModel     $listModel,
        private readonly ?FilterModel  $filterModel,
    ) {}

    /**
     * @deprecated Use {@see getType()} instead
     */
    public function getAlias(): string
    {
        return $this->getType();
    }

    public function getType(): string
    {
        return $this->type;
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

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFilterModel(): ?FilterModel
    {
        return $this->filterModel;
    }
}