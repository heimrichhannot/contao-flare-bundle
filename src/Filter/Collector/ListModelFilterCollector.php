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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Collects the published tl_flare_filter rows of a list model as Filter DTOs,
 * translating each row through its element's transformers.
 */
readonly class ListModelFilterCollector
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterElementResolver     $filterElementResolver,
        private FilterTransformerResolver $filterTransformerResolver,
        private ListTypeRegistry          $listTypeRegistry,
    ) {}

    /**
     * @return array<string, Filter>|null
     */
    public function collect(ListModel $listModel): ?array
    {
        if (!$listModel->id || !$table = $listModel::getTable()) {
            return null;
        }

        if (!$this->listTypeRegistry->get((string) $listModel->type)?->getService()) {
            return null;
        }

        Controller::loadDataContainer($table);

        $filters = [];

        /** @var FilterModel $model */
        foreach (FilterModel::findByPid((int) $listModel->id, published: true) as $model)
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
