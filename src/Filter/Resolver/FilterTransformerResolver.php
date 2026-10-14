<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs an element's source transformers ({@see TransformerContract}) to translate a stored
 * source (e.g. a FilterModel) into canonical config values. The configured transformer map
 * is memoized per element class; listeners extend it via {@see FilterTransformerEvent}.
 */
final class FilterTransformerResolver
{
    /**
     * @var array<class-string, TransformerResolver>
     */
    private array $resolvers = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     */
    public function transform(FilterElementInterface $element, string $type, object $source): ?array
    {
        $cacheKey = \sprintf('%s@%s', $type, $element::class);

        if (!isset($this->resolvers[$cacheKey]))
        {
            $resolver = new TransformerResolver();

            if ($element instanceof TransformerContract) {
                $element->configureTransformers($resolver);
            }

            $this->eventDispatcher->dispatch(new FilterTransformerEvent($resolver, $element, $type));

            $this->resolvers[$cacheKey] = $resolver;
        }

        if (!$transformer = $this->resolvers[$cacheKey]->resolve($source)) {
            return null;
        }

        $config = new ConfigBuilder();

        $transformer($config, $source);

        return $config->all();
    }
}
