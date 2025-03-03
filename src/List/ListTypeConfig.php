<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;
use HeimrichHannot\FlareBundle\List\Type\AbstractListType;

class ListTypeConfig implements ConfigInterface
{
    public const TAG = 'huh.flare.list_type';

    public function __construct(
        private         $service,
        private array   $attributes = [],
        private ?string $palette = null,
    ) {}

    public function getService(): AbstractListType
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

    public function getPalette(): ?string
    {
        return $this->palette;
    }

    public function setPalette(?string $palette): void
    {
        $this->palette = $palette;
    }
}