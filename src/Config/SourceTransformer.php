<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

/**
 * Memoizes one {@see TransformerMap} per key, filled once via a configurator closure, and runs the
 * transformer matching a stored source against it. The transformer counterpart of {@see SchemaResolver}:
 * domain concerns (contract guards, events, exception wrapping) live in the thin wrappers injecting this
 * service.
 */
final class SourceTransformer
{
    /**
     * @var array<string, TransformerMap>
     */
    private array $maps = [];

    /**
     * @param \Closure(TransformerMap): void $configure Runs once per $key (memoized).
     * @param object $source The stored source, e.g. a model.
     *
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     */
    public function transform(string $key, \Closure $configure, object $source): ?array
    {
        if (!isset($this->maps[$key]))
        {
            $map = new TransformerMap();
            $configure($map);
            $this->maps[$key] = $map;
        }

        if (!$transformer = $this->maps[$key]->resolve($source)) {
            return null;
        }

        $transformer($config = new ConfigBuilder(), $source);

        return $config->all();
    }
}
