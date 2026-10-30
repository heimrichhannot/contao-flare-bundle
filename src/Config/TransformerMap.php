<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

/**
 * Maps source classes to transformers translating a stored source object into canonical
 * config values. Configured declaratively per element/type class and extensible via events.
 */
final class TransformerMap
{
    /**
     * @var array<class-string, TransformerInterface|(callable(ConfigBuilder, object): void)>
     */
    private array $transformers = [];

    /**
     * Sets the transformer for a source class, replacing a previous one, so event listeners can
     * override the defaults of an element, control or list driver.
     *
     * @param class-string $sourceClass
     * @param TransformerInterface|callable(ConfigBuilder $config, object $source): void $transformer
     */
    public function set(string $sourceClass, TransformerInterface|callable $transformer): self
    {
        $this->transformers[$sourceClass] = $transformer;

        return $this;
    }

    /**
     * Finds the transformer set for the source's exact class, falling back to the first one
     * matching by inheritance (subclasses, interfaces); null if none matches.
     * The exact-class fast path lets a specific registration win over an earlier base-class one.
     *
     * @param object $source The stored source object to be transformed.
     *
     * @return TransformerInterface|(callable(ConfigBuilder $config, object $source): void)|null
     */
    public function find(object $source): TransformerInterface|callable|null
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
