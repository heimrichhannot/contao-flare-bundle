<?php

namespace HeimrichHannot\FlareBundle\FlareCallback;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ServiceConfigInterface;

class FlareCallbackConfig implements ServiceConfigInterface
{
    public const TAG = 'huh.flare.flare_callback';
    public const TAG_FILTER_CALLBACK = 'huh.flare.flare_callback.filter';
    public const TAG_LIST_CALLBACK = 'huh.flare.flare_callback.list';

    public function __construct(
        private object  $service,
        private array   $attributes = [],
        private ?string $filterElementAlias = null,
        private ?string $target = null,
        private ?string $method = null,
        private int     $priority = 0,
    ) {}

    public function getService(): object
    {
        return $this->service;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getFilterElementAlias(): ?string
    {
        return $this->filterElementAlias;
    }

    public function setFilterElementAlias(?string $filterElementAlias): void
    {
        $this->filterElementAlias = $filterElementAlias;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): void
    {
        $this->target = $target;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }
}