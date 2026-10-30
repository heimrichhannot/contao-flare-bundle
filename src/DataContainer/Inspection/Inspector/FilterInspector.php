<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection\Inspector;

use Contao\CoreBundle\Framework\ContaoFramework;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\InspectedRows;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\ListInspection;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Form\Factory\FilterFormBuilderFactory;
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
 * Cost: a list is inspected as a whole, once per request (see {@see inspect()}), on every backend view that shows its
 * filter issues — the filter overview, the filter rows and a filter's edit form. Each filter's conditions are built,
 * and each controlled filter's form, which loads its choices: about the work of one frontend view of the list. A
 * field value choice reads the distinct values of its column, a full scan on large tables. Accepted, so the backend
 * reports exactly what fails in the frontend.
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
        private readonly FilterFormBuilderFactory $filterFormBuilderFactory,
        private readonly ListSpecBuilderFactory   $listSpecBuilderFactory,
        private readonly TranslatorInterface      $translator,
        private readonly LoggerInterface          $logger,
        private readonly ContaoFramework          $framework,
        private readonly bool                     $debug,
    ) {}

    public function inspect(ListModel $listModel): ListInspection
    {
        $id = (int) $listModel->id;

        return $this->inspections[$id] ??= $this->inspectModels(
            $this->framework->getAdapter(FilterModel::class)->findBy(['pid=?', 'tstamp>0'], [$id], ['order' => 'sorting'])
                ?? [],
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
        $this->checkFilters($this->buildScope($buildScope, $rows), $rows);

        return $rows->toInspection();
    }

    /**
     * Builds the row's filter like the collector does, the FilterCollectedEvent included; an issue is recorded on the
     * row instead. A FlareException fails the list at runtime (broken); any other exception, which only a listener
     * can throw, fails the request (programming error).
     */
    private function build(FilterModel $model, InspectedRows $rows): ?Filter
    {
        try
        {
            $filter = $this->filterFactory->createFromFilterModel($model);

            return $this->eventDispatcher->dispatch(new FilterCollectedEvent($filter, $model))->filter;
        }
        catch (FlareException $e)
        {
            $rows->addIssue((int) $model->id, FilterIssueKind::Broken, self::reason($e));
        }
        catch (\Exception $e)
        {
            $rows->addIssue((int) $model->id, FilterIssueKind::ProgrammingError, self::reason($e));
        }
        catch (\Error $e)
        {
            $rows->addIssue(
                (int) $model->id,
                FilterIssueKind::ProgrammingError,
                $this->caught($e, "filter {$model::getTable()}.{$model->id}"),
            );
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
     * Builds the list the way the runtime does: with the published rows that could be built, the first per alias
     * only (the collector fails the list for any later one), so the driver's buildList() and its listeners see the
     * same filters. Null if the list cannot be built: an \Exception there is the list's problem, not a filter's (the
     * list driver reports its configuration); an \Error counts as a programming error without a row.
     *
     * @param \Closure(array<string, Filter>): ListSpec $buildScope
     */
    private function buildScope(\Closure $buildScope, InspectedRows $rows): ?ListSpec
    {
        $filters = [];

        foreach ($rows->getFilters() as $id => $filter)
        {
            if ($rows->isPublished($id)) {
                $filters[$filter->alias] ??= $filter;
            }
        }

        try
        {
            return $buildScope($filters);
        }
        catch (\Exception)
        {
            return null;
        }
        catch (\Error $e)
        {
            $this->caught($e, 'the list');
            $rows->addIssue(null, FilterIssueKind::ProgrammingError, $e->getMessage());

            return null;
        }
    }

    /**
     * Checks every filter of the list — its rows and the filters the driver or a listener added — and the rows that
     * are not part of it (unpublished rows, later duplicates), with the list as theirs. Without a list, only whether
     * each built row is effective.
     *
     * A filter of the list belongs to a row if it is the row's built filter (a FilterCollectedEvent listener may
     * have replaced it without a source), else if its source names the row.
     */
    private function checkFilters(?ListSpec $spec, InspectedRows $rows): void
    {
        $rowOf = [];

        foreach ($rows->getFilters() as $id => $filter) {
            $rowOf[\spl_object_id($filter)] = $id;
        }

        /** @var list<array{Filter, int|null}> $filters Each filter to check with its row id. */
        $filters = [];
        $inList = [];

        foreach ($spec->filters ?? [] as $filter)
        {
            $id = $rowOf[\spl_object_id($filter)] ?? $rows->idOf($filter->source);
            $filters[] = [$filter, $id];

            if ($id !== null) {
                $inList[$id] = true;
            }
        }

        foreach ($rows->getFilters() as $id => $filter)
        {
            if (!isset($inList[$id])) {
                $filters[] = [$filter, $id];
            }
        }

        foreach ($spec->failures ?? [] as $failure) {
            $rows->addIssue($rows->idOf($failure->source), FilterIssueKind::Broken, $failure->getReason());
        }

        foreach ($filters as [$filter, $id])
        {
            $found = [];

            $checks = [
                $spec ? $this->checkConditions($spec, $filter) : null,
                $spec && $filter->control !== null ? $this->checkForm($spec, $filter) : null,
                $this->checkEffect($filter),
            ];

            foreach ($checks as $issue)
            {
                if ($issue !== null) {
                    $found[$issue[0]->value] ??= $issue;
                }
            }

            foreach ($found as [$kind, $reason]) {
                $rows->addIssue($id, $kind, $reason);
            }
        }
    }

    /**
     * Builds the filter's conditions with its default value: the fixed value without a control, none with one (with
     * a control, the list retries without the value). An abort ("matches nothing") is no issue.
     *
     * @return array{FilterIssueKind, string}|null
     */
    private function checkConditions(ListSpec $spec, Filter $filter): ?array
    {
        $value = $filter->control !== null ? null : $filter->value;

        try
        {
            $this->filterInvoker->invoke(new ElementScope($spec, $filter, new ConfigurationContext()), $value);
        }
        catch (AbortFilteringException)
        {
            return null;
        }
        catch (\Exception $e)
        {
            return [FilterIssueKind::Broken, self::reason($e)];
        }
        catch (\Error $e)
        {
            return [FilterIssueKind::ProgrammingError, $this->caught($e, self::describe($filter))];
        }

        return null;
    }

    /**
     * Builds the control's form the way the filter form does, the {@see FilterControlBuildFormEvent} included. A
     * control that cannot build its form fails the list (broken); any other exception of a listener fails the request
     * (programming error).
     *
     * @return array{FilterIssueKind, string}|null
     */
    private function checkForm(ListSpec $spec, Filter $filter): ?array
    {
        try
        {
            $this->filterFormBuilderFactory->create(new ControlScope($spec, $filter, new ConfigurationContext()));
        }
        catch (FilterException $e)
        {
            return [FilterIssueKind::Broken, self::reason($e)];
        }
        catch (\Exception $e)
        {
            return [FilterIssueKind::ProgrammingError, self::reason($e)];
        }
        catch (\Error $e)
        {
            return [FilterIssueKind::ProgrammingError, $this->caught($e, self::describe($filter))];
        }

        return null;
    }

    /**
     * Asks the filter's element why it restricts nothing ({@see IneffectiveFilterContract}); an \Exception there
     * is no answer.
     *
     * @return array{FilterIssueKind, string}|null
     */
    private function checkEffect(Filter $filter): ?array
    {
        $element = $filter->element->component;

        if (!$element instanceof IneffectiveFilterContract) {
            return null;
        }

        try
        {
            $reason = $element->getIneffectiveReason($filter)?->trans($this->translator);
        }
        catch (\Exception)
        {
            return null;
        }
        catch (\Error $e)
        {
            return [FilterIssueKind::ProgrammingError, $this->caught($e, self::describe($filter))];
        }

        return $reason === null ? null : [FilterIssueKind::Ineffective, $reason];
    }

    /**
     * Logs an \Error caught outside debug mode and returns its message; rethrows it in debug mode.
     */
    private function caught(\Error $error, string $subject): string
    {
        if ($this->debug) {
            throw $error;
        }

        $this->logger->critical(
            \sprintf('[FLARE] Programming error in %s: %s', $subject, $error->getMessage()),
            ['exception' => $error],
        );

        return $error->getMessage();
    }

    private static function describe(Filter $filter): string
    {
        return $filter->source !== null ? "filter {$filter->source}" : "filter \"{$filter->alias}\"";
    }

    private static function reason(\Exception $exception): string
    {
        return (new FilterFailure(null, $exception->getMessage(), $exception))->getReason();
    }
}
