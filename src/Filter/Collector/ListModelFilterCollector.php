<?php

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\List\ListDataSource;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;

readonly class ListModelFilterCollector implements FilterCollectorInterface
{
    public function __construct(
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    public function supports(ListDataSource $dataSource): bool
    {
        return $dataSource instanceof ListModel;
    }

    public function collect(ListDataSource $dataSource): ?FilterDefinitionCollection
    {
        if (!$dataSource instanceof ListModel) {
            throw new \InvalidArgumentException('The given data source is not a list model.');
        }

        if (!$dataSource->id || !$table = $dataSource->getTable()) {
            return null;
        }

        if (!$this->listTypeRegistry->get($dataSource->getListType())?->getService()) {
            return null;
        }

        Controller::loadDataContainer($table);

        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        $filterModels = FilterModel::findByPid($dataSource->id, published: true) ?? [];
        $collection = new FilterDefinitionCollection();

        // $addedFilters = [];

        foreach ($filterModels as $filterModel)
            // Collect filters defined in the backend
        {
            if (!$filterModel->published) {
                continue;
            }

            $filterDefinition = FilterDefinition::fromFilterModel($filterModel);

            $collection->add($filterDefinition);

            // $addedFilters[] = $filterModel->type;
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
            //         listModel: $dataSource,
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

        return $collection;
    }
}