<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\ListTransformerEvent;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs a list type's source transformers ({@see TransformerContract}) to translate a stored
 * source (e.g. a ListModel) into canonical config values. The configured transformer map
 * is memoized per type class; listeners extend it via {@see ListTransformerEvent}.
 */
final class ListTransformerResolver
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
    public function transform(ListDriverInterface $driver, object $source): ?array
    {
        if (!isset($this->resolvers[$driver::class]))
        {
            $resolver = new TransformerResolver();

            if ($driver instanceof TransformerContract) {
                $driver->configureTransformers($resolver);
            }

            $this->eventDispatcher->dispatch(new ListTransformerEvent($resolver, $driver));

            $this->resolvers[$driver::class] = $resolver;
        }

        if (!$transformer = $this->resolvers[$driver::class]->resolve($source)) {
            return null;
        }

        $transformer($config = new ConfigBuilder(), $source);

        return $config->all();
    }
}
