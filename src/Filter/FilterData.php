<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

/**
 * Runtime data of one filter invocation.
 *
 * Holds either a single field's value or a compound filter's named field values — never both,
 * mirroring the mount decision in {@see Factory\FilterSetFactory}: an element that declares
 * {@see FilterFormBuilderInterface::single()} mounts flat under the filter's alias, while an
 * element adding children mounts as a compound sub-form.
 *
 * @api
 */
final readonly class FilterData implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(
        private mixed $single = null,
        private bool  $hasSingle = false,
        private array $values = [],
    ) {}

    /**
     * No runtime data at all, e.g., in non-interactive contexts.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * Data of a single-field filter. A `null` value is still "supplied" — see {@see hasSingle()}.
     */
    public static function single(mixed $value): self
    {
        return new self(single: $value, hasSingle: true);
    }

    /**
     * Data of a compound filter, keyed by the local field names declared in buildForm().
     *
     * @param array<string, mixed> $values
     */
    public static function of(array $values): self
    {
        return new self(values: $values);
    }

    /**
     * Whether a single value was supplied at all, which distinguishes a submitted `null`
     * from a filter that was never submitted.
     */
    public function hasSingle(): bool
    {
        return $this->hasSingle;
    }

    public function getSingleValue(mixed $default = null): mixed
    {
        return $this->hasSingle ? $this->single : $default;
    }

    /**
     * Whether the named field was supplied at all, which distinguishes a submitted `null`
     * from a field that was never submitted.
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->has($name) ? $this->values[$name] : $default;
    }

    /**
     * @return array<string, mixed> The named field values; always empty for single-field data.
     */
    public function all(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return !$this->hasSingle && !$this->values;
    }

    /**
     * Iterates the named field values only.
     *
     * @return \Traversable<string, mixed>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->values);
    }

    /**
     * Counts the named field values only.
     */
    public function count(): int
    {
        return \count($this->values);
    }

    /**
     * Stable representation for hashing/caching. Keeps the single value in its own slot so a
     * named field called "single" cannot collide with it.
     *
     * @return array{hasSingle: bool, single: mixed, values: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'hasSingle' => $this->hasSingle,
            'single' => $this->single,
            'values' => $this->values,
        ];
    }
}
