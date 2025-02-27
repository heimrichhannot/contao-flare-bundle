<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;

class ListTypeConfig implements ConfigInterface
{
    public const TAG = 'huh.flare.list_type';

    public function __construct(
        private       $service,
        private array $attributes = [],
    ) {}

    public function getService()
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
}