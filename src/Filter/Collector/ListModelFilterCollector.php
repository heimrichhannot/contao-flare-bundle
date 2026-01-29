<?php

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;

readonly class ListModelFilterCollector implements FilterCollectorInterface
{
    public function __construct(
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    public function supports(ListDataSourceInterface $dataSource): bool
    {
        return $dataSource instanceof ListModel;
    }

    public function collect(ListDataSourceInterface $dataSource): ?FilterDefinitionCollection
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

        /** @var \Traversable<int, FilterModel> $filterModels */
        $filterModels = FilterModel::findByPid($dataSource->id, published: true);
        $collection = new FilterDefinitionCollection();

        foreach ($filterModels as $filterModel)
            // Collect filters defined in the backend
        {
            if (!$filterModel->published) {
                continue;
            }

            $filterDefinition = FilterDefinition::fromFilterModel($filterModel);

            $key = $filterDefinition->getFilterFormFieldName()
                ?: "_.{$filterModel::getTable()}.{$filterModel->id}";

            $collection->set($key, $filterDefinition);
        }

        return $collection;
    }
}