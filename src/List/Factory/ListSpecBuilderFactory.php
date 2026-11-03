<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates ListSpecBuilders from a driver. A builder for a stored tl_flare_list model, with its published filters
 * and filter failures pre-added, comes from {@see \HeimrichHannot\FlareBundle\Contao\Factory\ListSpecBuilderFactory}.
 */
final readonly class ListSpecBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListDriverResolver       $listDriverResolver,
        private ListSpecFactory          $specFactory,
    ) {}

    public function create(
        ListDriverInterface|string $driver,
        ?ListModel                 $model = null,
        ?string                    $source = null,
    ): ListSpecBuilder {
        return new ListSpecBuilder(
            listDriverResolver: $this->listDriverResolver,
            specFactory: $this->specFactory,
            eventDispatcher: $this->eventDispatcher,
            driver: $driver,
            model: $model,
            source: $source,
        );
    }
}
