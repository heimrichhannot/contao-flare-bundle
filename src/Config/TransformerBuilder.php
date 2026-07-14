<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

/**
 * Maps source classes to transformers translating a stored source object into canonical
 * config values. Configured declaratively per element/type class and extensible via events.
 */
final class TransformerBuilder
{
    /**
     * @var array<class-string, callable(object, ConfigBuilder): void>
     */
    private array $transformers = [];

    /**
     * Registers a transformer for a source class. Registering the same class again replaces
     * the previous transformer, so event listeners can override element defaults.
     *
     * @param class-string $sourceClass
     * @param callable(object $source, ConfigBuilder $config): void $transformer
     */
    public function for(string $sourceClass, callable $transformer): self
    {
        $this->transformers[$sourceClass] = $transformer;

        return $this;
    }

    /**
     * Returns the first registered transformer whose source class matches the given source,
     * or null if none matches.
     *
     * @return (callable(object, ConfigBuilder): void)|null
     */
    public function resolve(object $source): ?callable
    {
        foreach ($this->transformers as $sourceClass => $transformer)
        {
            if ($source instanceof $sourceClass) {
                return $transformer;
            }
        }

        return null;
    }
}
