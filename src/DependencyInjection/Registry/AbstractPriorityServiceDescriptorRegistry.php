<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

/**
 * An abstract registry class that can be used to register service configurations.
 *
 * @internal For internal use only. API might change without notice.
 *
 * @template TDescriptor of ServiceDescriptorInterface
 * @template TNamespace of string
 * @template TKey of string
 * @template TPrio of int
 */
abstract class AbstractPriorityServiceDescriptorRegistry
{
    /**
     * @var array<TNamespace, array<TKey, array<TPrio, TDescriptor[]>>
     */
    private array $elements = [];

    /**
     * Returns the class name of the config class.
     *
     * @return class-string<ServiceDescriptorInterface>
     */
    abstract public function getDescriptorClass(): string;

    /**
     * Registers a new service configuration under a TNamespace with a TKey and priority.
     *
     * @param TNamespace  $namespace
     * @param TKey        $key
     * @param TPrio       $priority
     * @param TDescriptor $descriptor
     */
    public function add(string $namespace, string $key, int $priority, ServiceDescriptorInterface $descriptor): static
    {
        if (!\is_a($descriptor, $this->getDescriptorClass())) {
            throw new \InvalidArgumentException('Config must be an instance of ' . $this->getDescriptorClass() . '.');
        }

        $this->elements[$namespace][$key][$priority][] = $descriptor;

        return $this;
    }

    /**
     * Removes a service configuration from the registry.
     */
    public function remove(string $namespace, string $key): static
    {
        unset($this->elements[$namespace][$key]);

        return $this;
    }

    /**
     * Checks if a set of service configurations is registered.
     *
     * @param TNamespace $namespace
     * @param ?TKey      $key
     */
    public function has(string $namespace, string $key = null): bool
    {
        if (\is_null($key))
        {
            return isset($this->elements[$namespace])
                && \is_array($this->elements[$namespace])
                && \array_filter($this->elements[$namespace]);
        }

        return isset($this->elements[$namespace][$key])
            && \is_array($this->elements[$namespace][$key])
            && \array_filter($this->elements[$namespace][$key]);
    }

    /**
     * Returns a specific set of service configurations by its TNamespace and TKey.
     *
     * @param TNamespace $namespace
     * @param TKey       $key
     * @return array<TPrio, TDescriptor[]>|null A priority-sorted array of service configurations.
     */
    public function get(string $namespace, string $key): ?array
    {
        return $this->elements[$namespace][$key] ?? null;
    }

    /**
     * @param TNamespace $namespace
     * @return array<TKey, array<TPrio, TDescriptor[]>>|null
     */
    public function getNamespace(string $namespace): ?array
    {
        return $this->elements[$namespace] ?? null;
    }

    /**
     * Returns a specific set of service configurations by its TNamespace and TKey.
     *
     * @return TDescriptor[]|null
     */
    public function getSorted(string $namespace, string $key): ?array
    {
        if (!$prioSorted = $this->get($namespace, $key)) {
            return null;
        }

        \krsort($prioSorted);

        $return = [];
        \array_walk_recursive(
            $prioSorted,
            static function (ServiceDescriptorInterface $element) use (&$return): void {
                $return[] = $element;
            }
        );

        return $return;
    }

    /**
     * Returns all registered service configurations.
     *
     * @return array<TNamespace, array<TKey, array<TPrio, TDescriptor[]>>
     */
    public function all(): array
    {
        return $this->elements;
    }
}