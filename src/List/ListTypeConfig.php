<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceConfigInterface;
use HeimrichHannot\FlareBundle\List\Type\AbstractListType;

class ListTypeConfig implements ServiceConfigInterface, PaletteContract
{
    public const TAG = 'huh.flare.list_type';

    public function __construct(
        private         $service,
        private array   $attributes = [],
        private ?string $dataContainer = null,
        private ?string $palette = null,
    ) {}

    /**
     * @noinspection PhpDocSignatureInspection
     * @return AbstractListType|object
     */
    public function getService(): object
    {
        return $this->service;
    }

    public function setService($service): void
    {
        $this->service = $service;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getDataContainer(): ?string
    {
        return $this->dataContainer;
    }

    public function setDataContainer(?string $dataContainer): void
    {
        $this->dataContainer = $dataContainer;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return $this->palette;
    }

    public function setPalette(?string $palette): void
    {
        $this->palette = $palette;
    }
}