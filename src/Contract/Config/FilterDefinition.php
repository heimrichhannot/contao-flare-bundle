<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\Model\FilterModelDocTrait;

class FilterDefinition
{
    use FilterModelDocTrait;

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

    public function setAlias(string $alias): void
    {
        $this->alias = $alias;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isIntrinsic(): bool
    {
        return $this->intrinsic;
    }

    public function setIntrinsic(bool $intrinsic): void
    {
        $this->intrinsic = $intrinsic;
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