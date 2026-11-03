<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterValueProvidersPass;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;

/**
 * Maps value classes to their one authoritative value provider.
 *
 * Populated at compile time by {@see RegisterValueProvidersPass}.
 */
final class ValueProviderRegistry
{
    /** @var array<class-string<ValueInterface>, ValueProviderInterface> */
    private array $providers = [];

    /**
     * @param class-string<ValueInterface> $valueClass The narrowed `provide()` return type.
     */
    public function add(ValueProviderInterface $provider, string $valueClass): self
    {
        $this->providers[$valueClass] = $provider;

        return $this;
    }

    public function has(?string $valueClass): bool
    {
        return $valueClass !== null && isset($this->providers[$valueClass]);
    }

    /**
     * @param class-string<ValueInterface>|null $valueClass
     */
    public function get(?string $valueClass): ?ValueProviderInterface
    {
        return $valueClass !== null ? ($this->providers[$valueClass] ?? null) : null;
    }
}
