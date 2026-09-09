<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterElementsPass;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;

/**
 * Maps filter element type aliases to their element services and registration attributes, with
 * a reverse map from element class to the types it is registered under.
 *
 * Populated at compile time by {@see RegisterFilterElementsPass}. Registering an instance
 * without a type ("inline") uses its class name as the type alias.
 */
final class FilterElementRegistry
{
    /**
     * @var array<string, array{
     *     service: FilterElementInterface,
     *     attribute: ?AsFilterElement,
     *     service_class: class-string,
     *     inline: bool
     * }>
     */
    private array $elements = [];

    /**
     * @var array<class-string, list<string>>
     */
    private array $typesByClass = [];

    /**
     * Registers a filter element under a type alias. Re-registering a type overrides it.
     * A null $type registers the instance inline under its class name.
     */
    public function add(FilterElementInterface $service, ?AsFilterElement $attribute = null, ?string $type = null): self
    {
        $inline = $type === null;
        $serviceClass = \get_class($service);
        $type ??= $serviceClass;

        $this->prune($type);

        $this->elements[$type] = [
            'service' => $service,
            'attribute' => $attribute,
            'service_class' => $serviceClass,
            'inline' => $inline,
        ];

        $this->typesByClass[$serviceClass][] = $type;

        return $this;
    }

    public function remove(string $type): self
    {
        $this->prune($type);

        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->elements[$type]);
    }

    public function getService(?string $type): ?FilterElementInterface
    {
        return $type !== null ? ($this->elements[$type]['service'] ?? null) : null;
    }

    public function getAttribute(?string $type): ?AsFilterElement
    {
        return $type !== null ? ($this->elements[$type]['attribute'] ?? null) : null;
    }

    public function isInline(string $type): bool
    {
        return $this->elements[$type]['inline'] ?? false;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return \array_keys($this->elements);
    }

    /**
     * Returns the types an element is registered under, in registration order. An object
     * argument matches only the exact registered instance — an unregistered inline instance of
     * a registered class yields no types. A class-string matches all registrations of that class.
     *
     * @param FilterElementInterface|class-string $serviceOrClass
     * @return list<string>
     */
    public function getTypes(FilterElementInterface|string $serviceOrClass): array
    {
        if (\is_string($serviceOrClass)) {
            return $this->typesByClass[$serviceOrClass] ?? [];
        }

        $types = [];

        foreach ($this->typesByClass[\get_class($serviceOrClass)] ?? [] as $type)
        {
            if (($this->elements[$type]['service'] ?? null) === $serviceOrClass) {
                $types[] = $type;
            }
        }

        return $types;
    }

    private function prune(string $type): void
    {
        if (!$entry = $this->elements[$type] ?? null) {
            return;
        }

        unset($this->elements[$type]);

        $class = $entry['service_class'];

        $types = \array_values(\array_filter(
            $this->typesByClass[$class] ?? [],
            static fn (string $registered): bool => $registered !== $type,
        ));

        if (!$types) {
            unset($this->typesByClass[$class]);

            return;
        }

        $this->typesByClass[$class] = $types;
    }
}
