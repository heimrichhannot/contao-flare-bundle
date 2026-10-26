<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Filter\FilterComponentInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs the source transformers ({@see TransformerContract}) of a filter component (element or control)
 * to translate a stored source (e.g. a FilterModel) into canonical config values. The configured
 * transformer map is memoized per type and component class; listeners extend a component's map via
 * {@see FilterTransformerEvent}.
 */
final class FilterTransformerResolver
{
    /**
     * @var array<string, TransformerResolver>
     */
    private array $resolvers = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param string $type The element type or the control type.
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     */
    public function transform(
        FilterComponentInterface $owner,
        string                   $type,
        object                   $source,
    ): ?array {
        $cacheKey = \sprintf('%s@%s', $type, $owner::class);

        if (!isset($this->resolvers[$cacheKey]))
        {
            $resolver = new TransformerResolver();

            if ($owner instanceof TransformerContract) {
                $owner->configureTransformers($resolver);
            }

            $this->eventDispatcher->dispatch(new FilterTransformerEvent($resolver, $owner, $type));

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
