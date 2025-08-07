<?php

namespace HeimrichHannot\FlareBundle\Registry\Descriptor;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterElementsPass;
use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceDescriptorInterface;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;

class FilterElementDescriptor implements ServiceDescriptorInterface, PaletteContract
{
    public const TAG = 'huh.flare.filter_element';

    /** @see RegisterFilterElementsPass::getFilterElementConfig */
    public function __construct(
        private         $service,
        private array   $attributes = [],
        private ?string $palette = null,
        private ?string $formType = null,
        private ?string $method = null,
        private ?array  $scopes = null,
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

    public function getScopes(): ?array
    {
        return $this->scopes;
    }

    public function setScopes(?array $scopes): void
    {
        $this->scopes = $scopes;
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

    public function isAvailableForContext(ContentContext $contentContext): bool
    {
        if (\is_null($scopes = $this->getScopes()))
        {
            return true;
        }

        if ($contentContext->isTwig())
            // Twig context is always available for now. Maybe change this in the future.
        {
            return true;
        }

        return \in_array($contentContext->getContext(), $scopes, true);
    }
}