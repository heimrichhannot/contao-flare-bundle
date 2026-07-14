<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

/**
 * Maps source classes to transformers translating a stored source object into canonical
 * config values. Configured declaratively per element/type class and extensible via events.
 */
final class TransformerResolver
{
    /**
     * @var array<class-string, TransformerInterface|(callable(object, ConfigBuilder): void)>
     */
    private array $transformers = [];

    /**
     * Registers a transformer for a source class. Registering the same class again replaces
     * the previous transformer, so event listeners can override element defaults.
     *
     * @param class-string $sourceClass
     * @param TransformerInterface|callable(ConfigBuilder $config, object $source): void $transformer
     */
    public function for(string $sourceClass, TransformerInterface|callable $transformer): self
    {
        $this->transformers[$sourceClass] = $transformer;

        return $this;
    }

    /**
     * Returns the transformer registered for the source's exact class, falling back to the
     * first registration matching by inheritance (subclasses, interfaces); null if none matches.
     * The exact-class fast path lets a specific registration win over an earlier base-class one.
     *
     * @param object $source The stored source object to be transformed.
     *
     * @return TransformerInterface|(callable(ConfigBuilder $config, object $source): void)|null
     */
    public function resolve(object $source): TransformerInterface|callable|null
    {
        if ($transformer = $this->transformers[$source::class] ?? null) {
            return $transformer;
        }

        foreach ($this->transformers as $sourceClass => $transformer)
        {
            if ($source instanceof $sourceClass) {
                return $transformer;
            }
        }

        return null;
    }
}
