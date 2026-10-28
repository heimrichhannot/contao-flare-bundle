<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection;

use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Finds the issues of a list's filters for the backend's filter list, filter overview and filter edit form
 * ({@see FilterIssueKind}): every saved filter row is inspected, published or not; only published rows and filters
 * without a row (added by the list driver or a listener) count toward the overview messages.
 *
 * Outside debug mode, an \Error thrown while building or checking a filter is caught, logged as critical and reported
 * as a programming error, so the editor can still reach the filter; in debug mode it escapes. This is the only place
 * the bundle catches \Error; the frontend still fails the request.
 *
 * @internal
 */
final class FilterInspector implements ResetInterface
{
    /** @var array<int, ListInspection> By list id, for the request. */
    private array $inspections = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterFactory            $filterFactory,
        private readonly FilterInvoker            $filterInvoker,
        private readonly ListSpecBuilderFactory   $listSpecBuilderFactory,
        private readonly TranslatorInterface      $translator,
        private readonly LoggerInterface          $logger,
        private readonly bool                     $debug,
    ) {}

    public function inspect(ListModel $listModel): ListInspection
    {
        $id = (int) $listModel->id;

        return $this->inspections[$id] ??= $this->inspectModels(
            FilterModel::findBy(['pid=?', 'tstamp>0'], [$id], ['order' => 'sorting']) ?? [],
            function (array $filters) use ($listModel): ListSpec {
                $builder = $this->listSpecBuilderFactory->create(
                    driver: (string) $listModel->type,
                    model: $listModel,
                    source: "{$listModel::getTable()}.{$listModel->id}",
                );

                foreach ($filters as $alias => $filter) {
                    $builder->addFilter($filter, (string) $alias);
                }

                return $builder->build();
            },
        );
    }

    public function reset(): void
    {
        $this->inspections = [];
    }

    /**
     * @param iterable<FilterModel> $models Every saved filter row of the list, in sorting order.
     * @param \Closure(array<string, Filter>): ListSpec $buildScope Builds the list with the given row filters (keyed
     *   by alias) added, the way the runtime does.
     */
    public function inspectModels(iterable $models, \Closure $buildScope): ListInspection
    {
        $rows = new InspectedRows();

        foreach ($models as $model)
        {
            $rows->add($model);

            if ($filter = $this->build($model, $rows)) {
                $rows->setFilter((int) $model->id, $filter);
            }
        }

        $this->markDuplicates($rows);

        return $rows->toInspection();
    }

    /**
     * Builds the row's filter like the collector does; an issue is recorded on the row instead.
     */
    private function build(FilterModel $model, InspectedRows $rows): ?Filter
    {
        try
        {
            $filter = $this->filterFactory->createFromFilterModel($model);

            return $this->eventDispatcher->dispatch(new FilterCollectedEvent($filter, $model))->filter;
        }
        catch (\Exception $e)
        {
            $rows->addIssue((int) $model->id, FilterIssueKind::Broken, self::reason($e));
        }
        catch (\Error $e)
        {
            $rows->addIssue((int) $model->id, FilterIssueKind::ProgrammingError, $this->caught($e, $model->id));
        }

        return null;
    }

    /**
     * Groups the rows by alias. Two or more published rows block the list: every row of the group is marked. With
     * one published row, only the unpublished ones are (they would block it once published); with none, no row is.
     */
    private function markDuplicates(InspectedRows $rows): void
    {
        foreach ($rows->aliasGroups() as $alias => $ids)
        {
            $published = \array_filter($ids, $rows->isPublished(...));

            if (\count($published) < 1) {
                continue;
            }

            if (\count($published) >= 2) {
                $rows->block((string) $alias);
            }

            foreach ($ids as $id)
            {
                if (\count($published) >= 2 || !$rows->isPublished($id)) {
                    $rows->addIssue($id, FilterIssueKind::DuplicateAlias, (string) $alias);
                }
            }
        }
    }

    /**
     * Logs an \Error caught outside debug mode and returns its message; rethrows it in debug mode.
     */
    private function caught(\Error $error, mixed $rowId): string
    {
        if ($this->debug) {
            throw $error;
        }

        $this->logger->critical(\sprintf(
            '[FLARE] Programming error in filter %s: %s',
            $rowId === null ? 'without a row' : FilterModel::getTable() . '.' . $rowId,
            $error->getMessage(),
        ), ['exception' => $error]);

        return $error->getMessage();
    }

    private static function reason(\Exception $exception): string
    {
        return (new FilterFailure(null, $exception->getMessage(), $exception))->getReason();
    }
}
