<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

use Contao\DataContainer;

/**
 * Fluent per-type tweaks for a single DCA field definition. Only applied while a record
 * of the configuring type is being edited, so shared fields stay untouched for other types.
 */
final class DcaFieldBuilder
{
    private array $definition = [];

    /**
     * @var callable|array|null
     */
    private mixed $options = null;

    /**
     * @var list<callable(mixed, ?DataContainer): mixed>
     */
    private array $load = [];

    /**
     * @var list<callable(mixed, ?DataContainer): mixed>
     */
    private array $save = [];

    public function inputType(string $inputType): self
    {
        $this->definition['inputType'] = $inputType;
        return $this;
    }

    /**
     * Merges values into the field's `eval` configuration.
     */
    public function eval(array $eval): self
    {
        $this->definition['eval'] = \array_merge($this->definition['eval'] ?? [], $eval);
        return $this;
    }

    /**
     * Deep-merges arbitrary keys (reference, default, sql, ...) into the field definition.
     */
    public function merge(array $definition): self
    {
        $this->definition = self::deepMerge($this->definition, $definition);
        return $this;
    }

    /**
     * Static options array or an options provider `fn(?DataContainer): array`.
     *
     * @param callable(?DataContainer): array|array $options
     */
    public function options(callable|array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Adds a load transform `fn(mixed $value, ?DataContainer $dc): mixed`.
     */
    public function load(callable $fn): self
    {
        $this->load[] = $fn;
        return $this;
    }

    /**
     * Adds a save transform `fn(mixed $value, ?DataContainer $dc): mixed`.
     */
    public function save(callable $fn): self
    {
        $this->save[] = $fn;
        return $this;
    }

    /**
     * @internal Called by {@see DcaBuilder::apply()} only.
     */
    public function applyTo(array &$definition): void
    {
        $definition = self::deepMerge($definition, $this->definition);

        if (\is_array($this->options))
        {
            $definition['options'] = $this->options;
            unset($definition['options_callback']);
        }
        elseif (\is_callable($this->options))
        {
            $options = $this->options;
            $definition['options_callback'] = static fn (?DataContainer $dc = null): array => $options($dc);
            unset($definition['options']);
        }

        foreach ($this->load as $load)
        {
            $definition['load_callback'] ??= [];
            $definition['load_callback'][] = static fn (mixed $value, ?DataContainer $dc = null): mixed => $load($value, $dc);
        }

        foreach ($this->save as $save)
        {
            $definition['save_callback'] ??= [];
            $definition['save_callback'][] = static fn (mixed $value, ?DataContainer $dc = null): mixed => $save($value, $dc);
        }
    }

    private static function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value)
        {
            if (\is_int($key)) {
                $base[] = $value;
                continue;
            }

            if (\is_array($value) && \is_array($base[$key] ?? null)) {
                $base[$key] = self::deepMerge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
