<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompoundBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates ListCompoundBuilders from a driver. A builder for a stored tl_flare_list model, with its published filters
 * and filter failures pre-added, comes from
 * {@see \HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory}.
 */
final readonly class ListCompoundBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListDriverResolver       $listDriverResolver,
        private ListCompoundFactory      $listCompoundFactory,
    ) {}

    public function create(
        ListDriverInterface|string $driver,
        ?ListModel                 $model = null,
        ?string                    $source = null,
    ): ListCompoundBuilder {
        return new ListCompoundBuilder(
            listDriverResolver: $this->listDriverResolver,
            listCompoundFactory: $this->listCompoundFactory,
            eventDispatcher: $this->eventDispatcher,
            driver: $driver,
            model: $model,
            source: $source,
        );
    }
}
