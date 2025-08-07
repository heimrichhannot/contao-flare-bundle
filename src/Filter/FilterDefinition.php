<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Model\DocumentsFilterModelTrait;

class FilterDefinition
{
    use DocumentsFilterModelTrait;

    public function __construct(
        private string $alias,
        private string $title,
        private bool   $intrinsic,
        private array  $properties = [],
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias, ?string &$og = null): static
    {
        $og = $this->alias;
        $this->alias = $alias;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function isIntrinsic(): bool
    {
        return $this->intrinsic;
    }

    public function setIntrinsic(bool $intrinsic): static
    {
        $this->intrinsic = $intrinsic;
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    public function hasProperty(string $key): bool
    {
        return \array_key_exists($key, $this->properties);
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'alias', 'elementAlias', 'type', 'title', 'intrinsic' => true,
            default => $this->hasProperty($name) && $this->getProperty($name) !== null,
        };
    }

    public function __set(string $key, mixed $value): void
    {
        match ($key) {
            'alias', 'elementAlias', 'type' => $this->setAlias($value),
            'title' => $this->setTitle($value),
            'intrinsic' => $this->setIntrinsic($value),
            default => $this->properties[$key] = $value,
        };
    }

    public function __get(string $key): mixed
    {
        return match ($key) {
            'alias', 'elementAlias', 'type' => $this->getAlias(),
            'title' => $this->getTitle(),
            'intrinsic' => $this->isIntrinsic(),
            default => $this->getProperty($key),
        };
    }

    public function getRow(): array
    {
        return \array_merge($this->properties, [
            'title' => $this->title,
            'type' => $this->alias,
            'published' => true,
            'intrinsic' => $this->intrinsic,
        ]);
    }
}