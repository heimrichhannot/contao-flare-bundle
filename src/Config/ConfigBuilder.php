<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

/**
 * Fluent accumulator for canonical config values, populated by transformers
 * ({@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}). Casting, deserialization,
 * and enum parsing happen declaratively at the call site — this builder only collects.
 */
final class ConfigBuilder implements ConfigBuilderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = []) {}

    public function set(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    /**
     * Returns the accumulated canonical config.
     *
     * @return array<string, mixed>
     *
     * @internal Drained by the framework (transformer resolver, list builder) only.
     */
    public function all(): array
    {
        return $this->config;
    }
}
