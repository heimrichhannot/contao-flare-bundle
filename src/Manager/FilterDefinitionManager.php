<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\ListFiltersCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterDefinitionManager
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    public function collectListModelFilterDefinitions(ListModel $listModel): ?FilterDefinitionCollection
    {
        if (!$listModel->id || !$table = $listModel->dc) {
            return null;
        }

        if (!$this->listTypeRegistry->get($listModel->type)?->getService()) {
            return null;
        }

        Controller::loadDataContainer($table);

        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        $filterModels = FilterModel::findByPid($listModel->id, published: true) ?? [];
        $collection = new FilterDefinitionCollection();

        $addedFilters = [];

        foreach ($filterModels as $filterModel)
            // Collect filters defined in the backend
        {
            if (!$filterModel->published) {
                continue;
            }

            $addedFilters[] = $filterModel->type;

            $filterDefinition = FilterDefinition::fromFilterModel($filterModel);

            $collection->add($filterDefinition);

            // $filterElementAlias = $filterModel->type;
            // if (!$descriptor = $this->filterElementRegistry->get($filterElementAlias)) {
            //     continue;
            // }
            // $service = $descriptor->getService();
            // Skip if the filter is not configured for the current context
            // if ($service instanceof InScopeContract)
            // {
            //     $inScopeConfig = new InScopeConfig(
            //         contentContext: $context,
            //         listModel: $listModel,
            //         filterModel: $filterModel,
            //         descriptor: $descriptor,
            //     );
            //
            //     if (!$service->isInScope($inScopeConfig)) {
            //         continue;
            //     }
            // }
            // /** @mago-expect lint:no-else-clause This else clause is mandatory. */
            // elseif (!$descriptor->isAvailableForContext($context))
            // {
            //     continue;
            // } todo(@ericges): Filter definitions do not discriminate between contexts.
            //                   This logic has to be moved to when the filters are invoked contextually.
        }

        /** @var ListFiltersCollectedEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new ListFiltersCollectedEvent(
                filters: $collection,
                listModel: $listModel,
            )
        );

        return $event->filters;
    }
}