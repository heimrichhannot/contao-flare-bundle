<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Memoizes one {@see OptionsResolver} per key, seeded once via a configurator closure, and
 * resolves config arrays against it. Domain concerns (contract guards, exception wrapping)
 * live in the thin wrappers injecting this service.
 */
final class SchemaResolver
{
    /**
     * @var array<string, OptionsResolver>
     */
    private array $resolvers = [];

    /**
     * @param \Closure(OptionsResolver): void $configure Runs once per $key (memoized).
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function resolve(string $key, \Closure $configure, array $config): array
    {
        if (!isset($this->resolvers[$key]))
        {
            $resolver = new OptionsResolver();
            $configure($resolver);
            $this->resolvers[$key] = $resolver;
        }

        return $this->resolvers[$key]->resolve($config);
    }
}
