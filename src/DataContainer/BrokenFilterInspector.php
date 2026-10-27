<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer;

use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Finds the filters of a list that are guaranteed to break it — that fail under every user input — for the backend's
 * filter list, list overview and filter edit form: the list's collection failures, and each filter whose conditions
 * cannot be built with its default value (the fixed value without a control, none with one; with a control, the list
 * retries without the value, SPEC_FILTER_FORMS.md §8.1). Unpublished filters are checked too, but never fail the list.
 *
 * @internal
 */
final class BrokenFilterInspector implements ResetInterface
{
    /** @var array<int, FilterDiagnosis> By list id, for the request. */
    private array $diagnoses = [];

    public function __construct(
        private readonly ListSpecBuilderFactory $listSpecBuilderFactory,
        private readonly FilterFactory          $filterFactory,
        private readonly FilterInvoker          $filterInvoker,
    ) {}

    public function inspect(ListModel $listModel): FilterDiagnosis
    {
        $id = (int) $listModel->id;

        return $this->diagnoses[$id] ??= $this->diagnose(
            fn (): ListSpec => $this->listSpecBuilderFactory->createFromListModel($listModel)->build(),
            FilterModel::findByPid($id, published: false),
        );
    }

    public function reset(): void
    {
        $this->diagnoses = [];
    }

    /**
     * @param \Closure(): ListSpec $getSpec Builds the list; if it cannot be built, nothing is marked (a list-level
     *   problem, not one of a filter).
     * @param iterable<FilterModel> $unpublished The list's unpublished filter rows.
     */
    public function diagnose(\Closure $getSpec, iterable $unpublished): FilterDiagnosis
    {
        try
        {
            $spec = $getSpec();
        }
        catch (\Exception)
        {
            return new FilterDiagnosis();
        }

        $rows = [];
        $listFailures = [];

        foreach ($spec->failures as $failure) {
            $this->record($failure, $rows, $listFailures);
        }

        foreach ($spec->filters as $filter)
        {
            if ($failure = $this->check($spec, $filter)) {
                $this->record($failure, $rows, $listFailures);
            }
        }

        foreach ($unpublished as $model)
        {
            $failure = $this->checkModel($spec, $model);

            if ($failure !== null && ($id = self::rowId($failure->source)) !== null) {
                $rows[$id] ??= $failure;
            }
        }

        return new FilterDiagnosis($rows, $listFailures);
    }

    /**
     * @param array<int, FilterFailure> $rows
     * @param list<FilterFailure> $listFailures
     */
    private function record(FilterFailure $failure, array &$rows, array &$listFailures): void
    {
        $listFailures[] = $failure;

        if (($id = self::rowId($failure->source)) !== null) {
            $rows[$id] ??= $failure;
        }
    }

    /**
     * Builds the filter's conditions with its default value; an abort ("matches nothing") is no failure.
     */
    private function check(ListSpec $spec, Filter $filter): ?FilterFailure
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
            return new FilterFailure($filter->source, $e->getMessage(), $e);
        }

        return null;
    }

    private function checkModel(ListSpec $spec, FilterModel $model): ?FilterFailure
    {
        try
        {
            $filter = $this->filterFactory->createFromFilterModel($model);
        }
        catch (\Exception $e)
        {
            return new FilterFailure("{$model::getTable()}.{$model->id}", $e->getMessage(), $e);
        }

        return $this->check($spec, $filter);
    }

    /**
     * The tl_flare_filter id of a failure's source, e.g. 42 of "tl_flare_filter.42"; null for any other source.
     */
    private static function rowId(?string $source): ?int
    {
        $prefix = FilterModel::getTable() . '.';

        if ($source === null || !\str_starts_with($source, $prefix)) {
            return null;
        }

        $id = \substr($source, \strlen($prefix));

        return \ctype_digit($id) ? (int) $id : null;
    }
}
