<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry\Descriptor;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceDescriptorInterface;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;

class ListTypeDescriptor implements ServiceDescriptorInterface
{
    public function __construct(
        private object  $service,
        private array   $attributes = [],
        private ?string $dataContainer = null,
    ) {}

    /**
     * @noinspection PhpDocSignatureInspection
     * @return AbstractListDriver|object
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

    public function getDataContainer(): ?string
    {
        return $this->dataContainer;
    }

    public function setDataContainer(?string $dataContainer): void
    {
        $this->dataContainer = $dataContainer;
    }
}
