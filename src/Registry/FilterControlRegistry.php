<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterControlsPass;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * Maps filter control names to their services and value classes.
 *
 * Populated at compile time by {@see RegisterFilterControlsPass}. The name is the value persisted in
 * `tl_flare_filter.control`; the empty string is reserved for "no control" (intrinsic).
 */
final class FilterControlRegistry
{
    /**
     * @var array<string, array{control: FilterControlInterface, value: class-string<ValueInterface>}>
     */
    private array $controls = [];

    /**
     * @param class-string<ValueInterface> $valueClass The narrowed `decode()` return type.
     *
     * @throws \InvalidArgumentException If the name is empty; the empty string means "no control".
     */
    public function add(FilterControlInterface $control, string $name, string $valueClass): self
    {
        if ($name === '') {
            throw new \InvalidArgumentException(\sprintf(
                'The filter control %s must not be registered under an empty name.',
                $control::class,
            ));
        }

        $this->controls[$name] = ['control' => $control, 'value' => $valueClass];

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->controls[$name]);
    }

    public function get(?string $name): ?FilterControlInterface
    {
        return $name !== null ? ($this->controls[$name]['control'] ?? null) : null;
    }

    /**
     * @return class-string<ValueInterface>|null
     */
    public function getValueClass(?string $name): ?string
    {
        return $name !== null ? ($this->controls[$name]['value'] ?? null) : null;
    }

    /**
     * The controls producing the given value class, in registration order.
     *
     * @param class-string<ValueInterface> $valueClass
     * @return list<string>
     */
    public function findNames(string $valueClass): array
    {
        $names = [];

        foreach ($this->controls as $name => $entry)
        {
            if ($entry['value'] === $valueClass) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return \array_keys($this->controls);
    }
}
