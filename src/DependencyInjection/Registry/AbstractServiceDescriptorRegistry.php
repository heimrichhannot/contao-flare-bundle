<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

/**
 * @internal For internal use only. API might change without notice.
 *
 * @template TDescriptor of ServiceDescriptorInterface
 * @template TNamespace of string
 */
abstract class AbstractServiceDescriptorRegistry
{
    /**
     * An associative array of aliases and their corresponding FlareFilterElementDTO instances.
     *
     * @var array<TNamespace, TDescriptor>
     */
    private array $elements = [];

    /**
     * Returns the class name of the config class.
     *
     * @return class-string<TDescriptor>
     */
    abstract public function getDescriptorClass(): string;

    /**
     * Registers a new filter element.
     *
     * @param TNamespace $alias
     * @param TDescriptor $descriptor
     *
     * @throws \InvalidArgumentException if the config is not an instance of the expected class.
     */
    public function add(string $alias, ServiceDescriptorInterface $descriptor): static
    {
        if (!\is_a($descriptor, $this->getDescriptorClass())) {
            throw new \InvalidArgumentException('Config must be an instance of ' . $this->getDescriptorClass() . '.');
        }

        $this->elements[$alias] = $descriptor;

        return $this;
    }

    /**
     * Removes a filter element from the registry.
     *
     * @param TNamespace $alias
     */
    public function remove(string $alias): static
    {
        unset($this->elements[$alias]);

        return $this;
    }

    /**
     * Checks if a filter element is registered.
     *
     * @param TNamespace $alias
     */
    public function has(string $alias): bool
    {
        return isset($this->elements[$alias]);
    }

    /**
     * Returns a specific filter element by its alias.
     *
     * @param ?TNamespace $alias
     * @return ?TDescriptor
     */
    public function get(?string $alias): ?ServiceDescriptorInterface
    {
        if ($alias === null) {
            return null;
        }

        return $this->elements[$alias] ?? null;
    }

    /**
     * Returns all registered filter elements.
     *
     * @return array<TNamespace, TDescriptor>
     */
    public function all(): array
    {
        return $this->elements;
    }

    /**
     * Returns all registered filter element aliases.
     *
     * @return TNamespace[]
     */
    public function keys(): array
    {
        return \array_keys($this->elements);
    }
}