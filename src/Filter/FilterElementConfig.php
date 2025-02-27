<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;

class FilterElementConfig implements ConfigInterface
{
    public const TAG = 'huh.flare.filter_element';

    public function __construct(
        private         $service,
        private array   $attributes = [],
        private ?string $formType = null,
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

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function setFormType(?string $formType): void
    {
        $this->formType = $formType;
    }

    public function hasFormType(): bool
    {
        $class = $this->getFormType();
        return $class !== null && \class_exists($class);
    }
}