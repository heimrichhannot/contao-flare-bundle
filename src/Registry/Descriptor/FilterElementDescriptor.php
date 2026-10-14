<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry\Descriptor;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterElementsPass;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceDescriptorInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;

class FilterElementDescriptor implements ServiceDescriptorInterface
{
    /** @see RegisterFilterElementsPass::getFilterElementConfig */
    public function __construct(
        private FilterElementInterface $service,
        private array                  $attributes = [],
        private ?bool                  $isTargeted = null,
        private bool                   $intrinsicOnly = false,
    ) {}

    public function getService(): FilterElementInterface
    {
        return $this->service;
    }

    public function setService(FilterElementInterface $service): void
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

    public function isTargeted(): ?bool
    {
        return $this->isTargeted;
    }

    /**
     * Whether the element never renders a form control and must be configured intrinsically.
     */
    public function isIntrinsicOnly(): bool
    {
        return $this->intrinsicOnly;
    }
}
