<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;

/**
 * A selection from an option set.
 *
 * Keys, not domain values. Choice keys are strings by construction (Symfony view data always is)
 * and their *meaning* is element-defined, so any further interpretation belongs in the element's
 * {@see ChoiceSourceContract::valueFromChoiceKeys()}.
 */
final readonly class ChoiceValue implements ValueInterface
{
    /** @var list<string> Non-empty, deduplicated, ascending by string comparison. */
    public array $keys;

    /**
     * @param array<array-key, mixed> $keys Submitted choice keys. Entries with no string
     *   representation, and empty strings, are dropped.
     */
    public function __construct(array $keys)
    {
        $strings = [];

        foreach ($keys as $key)
        {
            if ($key === null || \is_bool($key) || \is_array($key)) {
                continue;
            }

            if (\is_object($key) && !$key instanceof \Stringable) {
                continue;
            }

            if (($key = (string) $key) !== '') {
                $strings[] = $key;
            }
        }

        $strings = \array_values(\array_unique($strings));

        \sort($strings, \SORT_STRING);

        $this->keys = $strings;
    }

    /**
     * @param array<array-key, mixed> $keys
     */
    public static function tryFrom(array $keys): ?self
    {
        $value = new self($keys);

        return $value->keys === [] ? null : $value;
    }
}
