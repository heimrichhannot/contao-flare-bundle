<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;

class FilterElementConfig implements ConfigInterface
{
    public const TAG = 'huh.flare.filter_element';

    public function __construct(
        private         $service,
        private array   $attributes = [],
        private ?string $palette = null,
        private ?string $formType = null,
        private ?string $method = null
    ) {}

    /**
     * @noinspection PhpDocSignatureInspection
     * @return AbstractFilterElement|object
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

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function setFormType(?string $formType): void
    {
        $this->formType = $formType;
    }

    public function getPalette(): ?string
    {
        return $this->palette;
    }

    public function setPalette(?string $palette): void
    {
        $this->palette = $palette;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    public function hasFormType(): bool
    {
        $class = $this->getFormType();
        return $class !== null && \class_exists($class);
    }

    public function isIntrinsicRequired(): bool
    {
        return !$this->hasFormType();
    }
}