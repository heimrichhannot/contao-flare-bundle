<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Collects the published tl_flare_filter rows of a list model as Filter DTOs,
 * translating each row through its element's and control's transformers.
 */
readonly class ListModelFilterCollector
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterFactory             $filterFactory,
        private ListDriverRegistry        $listDriverRegistry,
        private LoggerInterface           $logger,
    ) {}

    /**
     * @throws FlareException If no list driver is registered for the list's type. A broken filter
     *   never throws, so backend callbacks building the ListSpec keep working.
     */
    public function collect(ListModel $listModel): ?CollectedFilters
    {
        if (!$listModel->id || !$table = $listModel::getTable()) {
            return null;
        }

        if (!$this->listDriverRegistry->getService((string) $listModel->type)) {
            throw new FlareException('No list driver found for type "' . $listModel->type . '"');
        }

        Controller::loadDataContainer($table);

        return $this->collectFromModels(FilterModel::findByPid((int) $listModel->id, published: true), $listModel);
    }

    /**
     * Creates the filters of the given models. A filter that cannot be created fails by its role
     * (SPEC_FILTER_FORMS.md §8.1): with a control it is skipped with a warning (fail open); without
     * one, the failure is logged as an error and recorded, failing the list (fail closed).
     *
     * @param iterable<FilterModel> $models
     */
    public function collectFromModels(iterable $models, ListModel $listModel): CollectedFilters
    {
        $filters = [];
        $failures = [];

        foreach ($models as $model)
        {
            if (!$model->published) {
                continue;
            }

            try
            {
                $filter = $this->filterFactory->createFromFilterModel($model);
            }
            catch (FlareException $e)
            {
                $message = \sprintf(
                    'Filter %s.%s of type "%s" on %s.%s could not be created: %s',
                    $model::getTable(),
                    $model->id,
                    $model->getFilterElementType(),
                    $listModel::getTable(),
                    $listModel->id,
                    $e->getMessage(),
                );

                if ((string) $model->formVariant !== '')
                {
                    $this->logger->warning('[FLARE] ' . $message . ' -- The filter is skipped.');

                    continue;
                }

                $this->logger->error('[FLARE] ' . $message . ' -- The list fails.');
                $failures[] = $message;

                continue;
            }

            $filter = $this->eventDispatcher->dispatch(new FilterCollectedEvent($filter, $model))->filter;

            $filters[$filter->alias] = $filter;
        }

        return new CollectedFilters($filters, $failures);
    }
}
