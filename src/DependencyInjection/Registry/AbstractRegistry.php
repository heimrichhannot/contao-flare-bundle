<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

abstract class AbstractRegistry
{
    /**
     * An associative array of aliases and their corresponding FlareFilterElementDTO instances.
     *
     * @var array<string, ConfigInterface>
     */
    private array $elements = [];

    /**
     * Returns the class name of the config class.
     *
     * @return class-string<ConfigInterface>
     */
    abstract public function getConfigClass(): string;

    /**
     * Registers a new filter element.
     */
    public function add(string $alias, ConfigInterface $config): static
    {
        if (!\is_a($config, $this->getConfigClass())) {
            throw new \InvalidArgumentException('Config must be an instance of ' . $this->getConfigClass() . '.');
        }

        $this->elements[$alias] = $config;

        return $this;
    }

    /**
     * Removes a filter element from the registry.
     */
    public function remove(string $alias): static
    {
        unset($this->elements[$alias]);

        return $this;
    }

    /**
     * Checks if a filter element is registered.
     */
    public function has(string $alias): bool
    {
        return isset($this->elements[$alias]);
    }

    /**
     * Returns a specific filter element by its alias.
     */
    public function get(string $alias): ?ConfigInterface
    {
        return $this->elements[$alias] ?? null;
    }

    /**
     * Returns all registered filter elements.
     *
     * @return array<string, ConfigInterface>
     */
    public function all(): array
    {
        return $this->elements;
    }

    /**
     * Returns all registered filter element aliases.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return \array_keys($this->elements);
    }
}