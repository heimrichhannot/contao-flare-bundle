<?php

namespace HeimrichHannot\FlareBundle\Registry\Descriptor;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterElementsPass;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceDescriptorInterface;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;

class FilterElementDescriptor implements ServiceDescriptorInterface, PaletteContract
{
    /** @see RegisterFilterElementsPass::getFilterElementConfig */
    public function __construct(
        private object  $service,
        private array   $attributes = [],
        private ?string $palette = null,
        private ?string $formType = null,
        private ?string $method = null,
        private ?bool   $isTargeted = null,
    ) {}

    /**
     * @noinspection PhpDocSignatureInspection
     * @return AbstractFilterElement|object
     */
    public function getService(): object
    {
        return $this->service;
    }

    public function setService(object $service): void
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

    public function getPalette(PaletteConfig $config): ?string
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

    public function isTargeted(): ?bool
    {
        return $this->isTargeted;
    }

    public function setIsTargeted(?bool $isTargeted): void
    {
        $this->isTargeted = $isTargeted;
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