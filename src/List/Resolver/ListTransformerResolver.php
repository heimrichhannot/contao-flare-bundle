<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\ListTransformerEvent;
use HeimrichHannot\FlareBundle\List\Type\ListTypeInterface;
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
    private array $builders = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     */
    public function transform(ListTypeInterface $typeService, ?string $type, object $source): ?array
    {
        if (!isset($this->builders[$typeService::class]))
        {
            $transformers = new TransformerResolver();

            if ($typeService instanceof TransformerContract) {
                $typeService->configureTransformers($transformers);
            }

            $this->eventDispatcher->dispatch(new ListTransformerEvent($transformers, $typeService, $type));

            $this->builders[$typeService::class] = $transformers;
        }

        if (!$transformer = $this->builders[$typeService::class]->resolve($source)) {
            return null;
        }

        $transformer($config = new ConfigBuilder(), $source);

        return $config->all();
    }
}
