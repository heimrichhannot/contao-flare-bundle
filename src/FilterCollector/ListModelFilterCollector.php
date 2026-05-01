<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterCollector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Collection\ConfiguredFilterCollection;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use HeimrichHannot\FlareBundle\Specification\Factory\ConfiguredFilterFactory;

readonly class ListModelFilterCollector implements FilterCollectorInterface
{
    public function __construct(
        private ConfiguredFilterFactory $configuredFilterFactory,
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    public function supports(ListDataSourceInterface $dataSource): bool
    {
        return $dataSource instanceof ListModel;
    }

    public function collect(ListDataSourceInterface $dataSource): ?ConfiguredFilterCollection
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
        $collection = new ConfiguredFilterCollection();

        foreach ($filterModels as $filterModel)
            // Collect filters defined in the backend
        {
            if (!$filterModel->published) {
                continue;
            }

            $configuredFilter = $this->configuredFilterFactory->create($filterModel);

            $key = $configuredFilter->getAlias()
                ?: "_.{$filterModel::getTable()}.{$filterModel->id}";

            $collection->set($key, $configuredFilter);
        }

        return $collection;
    }
}