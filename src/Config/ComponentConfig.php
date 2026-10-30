<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;

/**
 * The resolved, canonical config of one filter component (element or control).
 *
 * Reading a key the component's schema does not define throws {@see UndefinedConfigKeyError};
 * a defined option without default that was not set reads as null.
 *
 * @api
 */
final readonly class ComponentConfig
{
    /** @var array<string, mixed> */
    private array $values;

    /** @var list<string> */
    private array $defined;

    /**
     * @param array<string, mixed> $values Resolved values: scalars, arrays, and enums only.
     * @param list<string>|null $defined Option names the component's schema defines; null for the keys of $values.
     * @param string|null $owner The component class, for error messages.
     */
    public function __construct(
        array           $values = [],
        ?array          $defined = null,
        private ?string $owner = null,
    ) {
        $this->values = $values;
        $this->defined = $defined ?? \array_keys($values);
    }

    /**
     * @throws UndefinedConfigKeyError If the component's schema does not define the key.
     */
    public function get(string $key): mixed
    {
        if (\array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if (!\in_array($key, $this->defined, true)) {
            throw new UndefinedConfigKeyError($key, $this->defined, $this->owner);
        }

        return null;
    }

    /**
     * Whether the key holds a value; false for an option defined without default and not set.
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
