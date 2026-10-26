<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterComponentInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs the source transformers ({@see TransformerContract}) of a filter component (element or control)
 * to translate a stored source (e.g. a FilterModel) into canonical config values. The transformer map is
 * memoized per type and component class; listeners extend a component's map via {@see FilterTransformerEvent}.
 */
final readonly class FilterTransformerResolver
{
    public function __construct(
        private SourceTransformer        $sourceTransformer,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param string $type The element type or the control type.
     * @param string|null $origin Provenance for the exception, e.g. "tl_flare_filter.42".
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     *
     * @throws FilterException If configuring or running the transformer fails.
     */
    public function transform(
        FilterComponentInterface $owner,
        string                   $type,
        object                   $source,
        ?string                  $origin = null,
    ): ?array {
        try
        {
            return $this->sourceTransformer->transform(
                \sprintf('%s@%s', $type, $owner::class),
                function (TransformerMap $transformers) use ($owner, $type): void {
                    if ($owner instanceof TransformerContract) {
                        $owner->configureTransformers($transformers);
                    }

                    $this->eventDispatcher->dispatch(new FilterTransformerEvent($transformers, $owner, $type));
                },
                $source,
            );
        }
        catch (\Throwable $e)
        {
            throw new FilterException(
                \sprintf(
                    '[FLARE] The config of "%s" could not be read from %s: %s',
                    $owner::class,
                    $source::class,
                    $e->getMessage(),
                ),
                previous: $e,
                method: $owner::class . '::configureTransformers',
                source: $origin,
            );
        }
    }
}
