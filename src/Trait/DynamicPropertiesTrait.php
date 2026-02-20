<?php

namespace HeimrichHannot\FlareBundle\Trait;

trait DynamicPropertiesTrait
{
    protected array $properties = [];

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $name, mixed $default = null): mixed
    {
        return $this->properties[$name] ?? $default;
    }

    public function hasProperty(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    protected function setProperty(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }

    public function issetProperty(string $name): bool
    {
        return $this->hasProperty($name) && $this->getProperty($name) !== null;
    }

    public function __isset(string $name): bool
    {
        return $this->issetProperty($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setProperty($name, $value);
    }

    public function __get(string $name)
    {
        return $this->getProperty($name);
    }
}