<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

/**
 * An abstract registry class that can be used to register service configurations.
 *
 * @template namespace of string
 * @template key of string
 * @template prio of int
 */
abstract class AbstractPriorityRegistry
{
    /**
     * @var array<namespace, array<key, array<prio, ServiceConfigInterface[]>>
     */
    private array $elements = [];

    /**
     * Returns the class name of the config class.
     *
     * @return class-string<ServiceConfigInterface>
     */
    abstract public function getConfigClass(): string;

    /**
     * Registers a new service configuration under a namespace with a key and priority.
     */
    public function add(string $namespace, string $key, int $priority, ServiceConfigInterface $config): static
    {
        if (!\is_a($config, $this->getConfigClass())) {
            throw new \InvalidArgumentException('Config must be an instance of ' . $this->getConfigClass() . '.');
        }

        $this->elements[$namespace][$key][$priority] = $config;

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
     */
    public function has(string $namespace, string $key = null): bool
    {
        if ($key) {
            return isset($this->elements[$namespace][$key])
                && \count(\array_filter(
                    $this->elements[$namespace][$key],
                    fn($list) =>\is_array($list) && \count($list) > 0
                )) > 0;
        }

        return isset($this->elements[$namespace]);
    }

    /**
     * @return array<prio, ServiceConfigInterface[]>|array<key, array<prio, ServiceConfigInterface[]>>|null
     */
    public function get(string $namespace, string $key = null): ?array
    {
        if ($key) {
            return $this->elements[$namespace][$key] ?? null;
        }

        return $this->elements[$namespace] ?? null;
    }

    /**
     * Returns a specific set of service configurations by its namespace and key.
     *
     * @return ServiceConfigInterface[]|null
     */
    public function getSorted(string $namespace, string $key): ?array
    {
        if (!$elements = $this->get($namespace, $key)) {
            return null;
        }

        \krsort($elements);

        $return = [];
        \array_walk_recursive($elements, static function ($element) use (&$return) {
            $return[] = $element;
        });

        return $return;
    }

    /**
     * Returns all registered service configurations.
     *
     * @return array<string, ServiceConfigInterface>
     */
    public function all(): array
    {
        return $this->elements;
    }
}