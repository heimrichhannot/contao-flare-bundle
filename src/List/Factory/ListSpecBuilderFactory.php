<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Collector\ListModelFilterCollector;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates ListSpecBuilders — from a stored tl_flare_list model with its published filters
 * pre-added, or programmatically from a driver.
 */
final readonly class ListSpecBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListDriverResolver       $listDriverResolver,
        private ListModelFilterCollector $filterCollector,
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

    /**
     * @throws FlareException In case the list driver cannot be resolved.
     */
    public function createFromListModel(ListModel $listModel): ListSpecBuilder
    {
        $builder = $this->create(
            driver: (string) $listModel->type,
            model: $listModel,
            source: $listModel::getTable() . '.' . $listModel->id,
        );

        foreach ($this->filterCollector->collect($listModel) ?? [] as $key => $filter) {
            $builder->addFilter($filter, (string) $key);
        }

        return $builder;
    }
}
