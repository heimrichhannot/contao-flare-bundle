<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\List\FilterFailure;
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
        private EventDispatcherInterface $eventDispatcher,
        private FilterFactory            $filterFactory,
        private ListDriverRegistry       $listDriverRegistry,
        private LoggerInterface          $logger,
    ) {}

    /**
     * @throws FlareException If no list driver is registered for the list's type. A filter that fails
     *   with a FlareException does not throw (it is recorded, see {@see collectFromModels()}),
     *   so backend callbacks building the ListSpec keep working; other throwables still escape.
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
     * Creates the filters of the given models. A filter whose element cannot be created — unknown type,
     * element config failing its schema, or an unreadable fixed value — fails the list as a whole
     * (SPEC_FILTER_FORMS.md §8.1), with or without a control: the element config bounds the whole list
     * (§4.2). So does a stored control that cannot be resolved, or whose config is broken. The failure is logged
     * as an error and recorded. An unreadable initial value never reaches this point;
     * {@see FilterFactory::createFromFilterModel()} keeps such a filter without the value.
     *
     * So does a FlareException from a {@see FilterCollectedEvent} listener; any other exception of a listener is a
     * bug and fails the request.
     *
     * Two filters sharing an alias fail the list too: the later one is recorded, never silently replacing
     * the earlier one.
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
                // A listener's FlareException fails the list like any filter failure; anything else is a bug.
                $filter = $this->eventDispatcher->dispatch(new FilterCollectedEvent($filter, $model))->filter;
            }
            catch (FlareException $e)
            {
                $failures[] = $this->fail($model, $listModel, $e->getMessage(), $e);

                continue;
            }

            if (isset($filters[$filter->alias]))
            {
                $failures[] = $this->fail($model, $listModel, \sprintf(
                    'The alias "%s" is already used by filter %s.',
                    $filter->alias,
                    $filters[$filter->alias]->source ?? 'another filter',
                ));

                continue;
            }

            $filters[$filter->alias] = $filter;
        }

        return new CollectedFilters($filters, $failures);
    }

    /**
     * Logs a filter failure that fails the list and returns it.
     */
    private function fail(
        FilterModel $model,
        ListModel   $listModel,
        string      $reason,
        ?\Exception $exception = null,
    ): FilterFailure {
        $message = \sprintf(
            'Filter %s.%s of type "%s" on %s.%s could not be created: %s',
            $model::getTable(),
            $model->id,
            $model->getFilterElementType(),
            $listModel::getTable(),
            $listModel->id,
            $reason,
        );

        $this->logger->error('[FLARE] ' . $message . ' -- The list fails.');

        return new FilterFailure("{$model::getTable()}.{$model->id}", $message, $exception);
    }
}
