<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Collects the published tl_flare_filter rows of a list model as Filter DTOs,
 * translating each row through its element's transformers.
 */
readonly class ListModelFilterCollector
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterElementRegistry     $filterElementRegistry,
        private FilterTransformerResolver $filterTransformerResolver,
        private ListDriverRegistry        $listDriverRegistry,
        private LoggerInterface           $logger,
    ) {}

    /**
     * @return array<string, Filter>|null
     */
    public function collect(ListModel $listModel): ?array
    {
        if (!$listModel->id || !$table = $listModel::getTable()) {
            return null;
        }

        if (!$this->listDriverRegistry->getService((string) $listModel->type)) {
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
            $type = $model->getFilterType();

            if (!$element = $this->filterElementRegistry->getService($type))
            {
                $this->logger->warning(\sprintf(
                    '[FLARE] No filter element registered for type "%s" — filter skipped. (%s)',
                    $type,
                    $source,
                ));

                continue;
            }

            $config = $this->filterTransformerResolver->transform($element, $model->getFilterType(), $model)
                ?? $model->row();

            $filter = new Filter(
                element: $element,
                type: $model->getFilterType(),
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
