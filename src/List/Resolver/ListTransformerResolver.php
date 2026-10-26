<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\ListDriverTransformerEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs a list driver's source transformers ({@see TransformerContract}) to translate a stored source
 * (e.g. a ListModel) into canonical list config values. The transformer map is memoized per type and driver
 * class; listeners extend it via {@see ListDriverTransformerEvent}.
 */
final readonly class ListTransformerResolver
{
    public function __construct(
        private SourceTransformer        $sourceTransformer,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param string|null $origin Provenance for the exception, e.g. "tl_flare_list.3".
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     *
     * @throws FlareException If configuring or running the transformer fails.
     */
    public function transform(
        ListDriverInterface $driver,
        string              $type,
        object              $source,
        ?string             $origin = null,
    ): ?array {
        try
        {
            return $this->sourceTransformer->transform(
                \sprintf('%s@%s', $type, $driver::class),
                function (TransformerMap $transformers) use ($driver, $type): void {
                    if ($driver instanceof TransformerContract) {
                        $driver->configureTransformers($transformers);
                    }

                    $this->eventDispatcher->dispatch(new ListDriverTransformerEvent($transformers, $driver, $type));
                },
                $source,
            );
        }
        catch (\Throwable $e)
        {
            throw new FlareException(
                \sprintf(
                    '[FLARE] The config of "%s" could not be read from %s: %s',
                    $driver::class,
                    $source::class,
                    $e->getMessage(),
                ),
                previous: $e,
                method: $driver::class . '::configureTransformers',
                source: $origin,
            );
        }
    }
}
