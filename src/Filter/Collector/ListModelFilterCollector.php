<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterElementResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListModelFilterCollector implements FilterCollectorInterface
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterElementResolver     $filterElementResolver,
        private FilterTransformerResolver $filterTransformerResolver,
        private ListTypeRegistry          $listTypeRegistry,
    ) {}

    public function supports(ListDataSourceInterface $dataSource): bool
    {
        return $dataSource instanceof ListModel;
    }

    public function collect(ListDataSourceInterface $dataSource): ?array
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

        $filters = [];

        /** @var FilterModel $model */
        foreach (FilterModel::findByPid((int) $dataSource->id, published: true) as $model)
            // Collect filters defined in the backend
        {
            if (!$model->published) {
                continue;
            }

            $source = "{$model::getTable()}.{$model->id}";

            if (!$element = $this->filterElementResolver->resolveType($model->getFilterType(), $source)) {
                continue;
            }

            $config = $this->filterTransformerResolver->transform($element, $model->getFilterType(), $model)
                ?? $model->row();

            $filter = new Filter(
                element: $model->getFilterType(),
                config: $config,
                alias: $model->getFilterFormName() ?: "_.{$source}",
                targetAlias: $model->getFilterTargetAlias() ?: null,
                source: $source,
            );

            $filter = $this->eventDispatcher->dispatch(new FilterCollectedEvent($filter, $model))->filter;

            $filters[$filter->alias] = $filter;
        }

        return $filters;
    }
}
