<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Inspection;

use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Model\FilterModel;

/**
 * The state of one inspection run of {@see Inspector\FilterInspector}: the list's filter rows, their built filters
 * and the issues found so far.
 *
 * @internal
 */
final class InspectedRows
{
    /** @var array<int, FilterModel> By tl_flare_filter id, in sorting order. */
    private array $models = [];

    /** @var array<int, FilterCompound> The built filter of each row that could be built. */
    private array $filters = [];

    /** @var array<int, list<FilterIssue>> By row id, in the order found; every issue, an equal one once. */
    private array $issues = [];

    /** @var array<string, int> Issues of filters without a row, by kind value. */
    private array $withoutRow = [];

    /** @var list<string> */
    private array $blockingAliases = [];

    public function add(FilterModel $model): void
    {
        $this->models[(int) $model->id] = $model;
    }

    /**
     * @return array<int, FilterModel> The list's filter rows, by tl_flare_filter id, in sorting order.
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function setFilter(int $id, FilterCompound $filter): void
    {
        $this->filters[$id] = $filter;
    }

    /**
     * @return array<int, FilterCompound> The built filters, by row id, in sorting order.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function has(int $id): bool
    {
        return isset($this->models[$id]);
    }

    public function isPublished(int $id): bool
    {
        return (bool) ($this->models[$id]->published ?? false);
    }

    /**
     * Adds an issue to the row; a row keeps every issue, but one equal in kind, reason and icon to an issue it already
     * has only once (the collector's failure and the condition check can report the same text).
     *
     * @param int|null $id The row id; null for a filter without a row of this list, whose issue only counts: each
     *   call counts one filter.
     * @param FilterIssueIcon|null $icon See {@see FilterIssue::$icon}.
     */
    public function addIssue(
        ?int $id,
        FilterIssueKind $kind,
        string $reason,
        ?FilterIssueIcon $icon = null,
    ): void
    {
        if ($id === null)
        {
            $this->withoutRow[$kind->value] = ($this->withoutRow[$kind->value] ?? 0) + 1;

            return;
        }

        foreach ($this->issues[$id] ?? [] as $issue)
        {
            if ($issue->kind === $kind && $issue->reason === $reason && $issue->icon === $icon) {
                return;
            }
        }

        $this->issues[$id][] = new FilterIssue($kind, $reason, $this->isPublished($id), $icon);
    }

    /**
     * @return list<FilterIssue> The row's issues found so far, in the order found.
     */
    public function issuesOf(int $id): array
    {
        return $this->issues[$id] ?? [];
    }

    /**
     * The id of the row of this list a filter source names, e.g. 42 of "tl_flare_filter.42"; null for any other
     * source.
     */
    public function idOf(?string $source): ?int
    {
        $prefix = FilterModel::getTable() . '.';

        if ($source === null || !\str_starts_with($source, $prefix)) {
            return null;
        }

        $suffix = \substr($source, \strlen($prefix));
        $id = \filter_var($suffix, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id !== false && $this->has($id) ? $id : null;
    }

    public function block(string $alias): void
    {
        $this->blockingAliases[] = $alias;
    }

    /**
     * The rows sharing an alias, by alias: a built row's alias is its filter's (after {@see
     * \HeimrichHannot\FlareBundle\Event\FilterCollectedEvent}), any other row's its form name.
     *
     * @return array<string, list<int>> Groups of two or more row ids.
     */
    public function aliasGroups(): array
    {
        $groups = [];

        foreach ($this->models as $id => $model)
        {
            try
            {
                $alias = $this->filters[$id]->alias ?? FilterModel::generateFormName($model);
            }
            catch (\RuntimeException)
            {
                continue;
            }

            $groups[$alias][] = $id;
        }

        return \array_filter($groups, static fn (array $ids): bool => \count($ids) > 1);
    }

    /**
     * Each row's issues ordered by kind, in the order found within a kind; a published row counts once per kind.
     */
    public function toInspection(): ListInspection
    {
        $rows = [];
        $counts = $this->withoutRow;

        foreach ($this->issues as $id => $issues)
        {
            foreach (FilterIssueKind::cases() as $kind)
            {
                $ofKind = \array_values(\array_filter(
                    $issues,
                    static fn (FilterIssue $issue): bool => $issue->kind === $kind,
                ));

                if ($ofKind === []) {
                    continue;
                }

                $rows[$id] = [...($rows[$id] ?? []), ...$ofKind];

                if ($this->isPublished($id)) {
                    $counts[$kind->value] = ($counts[$kind->value] ?? 0) + 1;
                }
            }
        }

        return new ListInspection($rows, $counts, $this->blockingAliases);
    }
}
