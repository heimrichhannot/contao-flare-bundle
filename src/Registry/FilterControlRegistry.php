<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterControlsPass;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * Maps filter control types to their services and value classes.
 *
 * Populated at compile time by {@see RegisterFilterControlsPass}. The type is the value persisted in
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
     * @throws \InvalidArgumentException If the type is empty; the empty string means "no control".
     */
    public function add(FilterControlInterface $control, string $type, string $valueClass): self
    {
        if ($type === '') {
            throw new \InvalidArgumentException(\sprintf(
                'The filter control %s must not be registered under an empty type.',
                $control::class,
            ));
        }

        $this->controls[$type] = ['control' => $control, 'value' => $valueClass];

        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->controls[$type]);
    }

    public function get(?string $type): ?FilterControlInterface
    {
        return $type !== null ? ($this->controls[$type]['control'] ?? null) : null;
    }

    /**
     * @return class-string<ValueInterface>|null
     */
    public function getValueClass(?string $type): ?string
    {
        return $type !== null ? ($this->controls[$type]['value'] ?? null) : null;
    }

    /**
     * The controls producing the given value class, in registration order.
     *
     * @param class-string<ValueInterface> $valueClass
     * @return list<string>
     */
    public function findTypes(string $valueClass): array
    {
        $types = [];

        foreach ($this->controls as $type => $entry)
        {
            if ($entry['value'] === $valueClass) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return \array_keys($this->controls);
    }
}
