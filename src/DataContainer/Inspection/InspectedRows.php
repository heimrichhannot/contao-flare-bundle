<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection;

use HeimrichHannot\FlareBundle\Filter\Filter;
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

    /** @var array<int, Filter> The built filter of each row that could be built. */
    private array $filters = [];

    /** @var array<int, array<string, FilterIssue>> By row id and kind value; the first issue of a kind wins. */
    private array $issues = [];

    /** @var array<string, int> Issues of filters without a row, by kind value. */
    private array $withoutRow = [];

    /** @var list<string> */
    private array $blockingAliases = [];

    public function add(FilterModel $model): void
    {
        $this->models[(int) $model->id] = $model;
    }

    public function setFilter(int $id, Filter $filter): void
    {
        $this->filters[$id] = $filter;
    }

    /**
     * @return array<int, Filter> The built filters, by row id, in sorting order.
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
     * @param int|null $id The row id; null for a filter without a row of this list, whose issue only counts.
     */
    public function addIssue(?int $id, FilterIssueKind $kind, string $reason): void
    {
        if ($id === null)
        {
            $this->withoutRow[$kind->value] = ($this->withoutRow[$kind->value] ?? 0) + 1;

            return;
        }

        $this->issues[$id][$kind->value] ??= new FilterIssue($kind, $reason, $this->isPublished($id));
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

        $id = \substr($source, \strlen($prefix));

        return \ctype_digit($id) && $this->has((int) $id) ? (int) $id : null;
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

    public function toInspection(): ListInspection
    {
        $rows = [];
        $counts = $this->withoutRow;

        foreach ($this->issues as $id => $issues)
        {
            foreach (FilterIssueKind::cases() as $kind)
            {
                if (!isset($issues[$kind->value])) {
                    continue;
                }

                $rows[$id][] = $issues[$kind->value];

                if ($this->isPublished($id)) {
                    $counts[$kind->value] = ($counts[$kind->value] ?? 0) + 1;
                }
            }
        }

        return new ListInspection($rows, $counts, $this->blockingAliases);
    }
}
