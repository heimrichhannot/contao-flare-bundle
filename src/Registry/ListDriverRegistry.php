<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterListDriversPass;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;

/**
 * Maps list driver type aliases to their driver services and registration attributes, with a
 * reverse map from driver class to the types it is registered under.
 *
 * Populated at compile time by {@see RegisterListDriversPass}. Registering an instance without
 * a type ("inline") uses its class name as the type alias.
 */
final class ListDriverRegistry
{
    /**
     * @var array<string, array{service: ListDriverInterface, attribute: ?AsListDriver, service_class: class-string, inline: bool}>
     */
    private array $drivers = [];

    /**
     * @var array<class-string, list<string>>
     */
    private array $typesByClass = [];

    /**
     * Registers a driver under a type alias. Re-registering a type overrides it.
     * A null $type registers the instance inline under its class name.
     */
    public function add(ListDriverInterface $service, ?AsListDriver $attribute = null, ?string $type = null): self
    {
        $inline = $type === null;
        $serviceClass = \get_class($service);
        $type ??= $serviceClass;

        $this->prune($type);

        $this->drivers[$type] = [
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
        return isset($this->drivers[$type]);
    }

    public function getService(?string $type): ?ListDriverInterface
    {
        return $type !== null ? ($this->drivers[$type]['service'] ?? null) : null;
    }

    public function getAttribute(?string $type): ?AsListDriver
    {
        return $type !== null ? ($this->drivers[$type]['attribute'] ?? null) : null;
    }

    public function isInline(string $type): bool
    {
        return $this->drivers[$type]['inline'] ?? false;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return \array_keys($this->drivers);
    }

    /**
     * Returns the types a driver is registered under, in registration order. An object argument
     * matches only the exact registered instance — an unregistered inline instance of a
     * registered class yields no types. A class-string matches all registrations of that class.
     *
     * @param ListDriverInterface|class-string $serviceOrClass
     * @return list<string>
     */
    public function getTypes(ListDriverInterface|string $serviceOrClass): array
    {
        if (\is_string($serviceOrClass)) {
            return $this->typesByClass[$serviceOrClass] ?? [];
        }

        $types = [];

        foreach ($this->typesByClass[\get_class($serviceOrClass)] ?? [] as $type)
        {
            if (($this->drivers[$type]['service'] ?? null) === $serviceOrClass) {
                $types[] = $type;
            }
        }

        return $types;
    }

    private function prune(string $type): void
    {
        if (!$entry = $this->drivers[$type] ?? null) {
            return;
        }

        unset($this->drivers[$type]);

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
