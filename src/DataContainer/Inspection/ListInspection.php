<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection;

/**
 * The issues of a list's filters ({@see Inspector\FilterInspector}).
 *
 * @internal
 */
final readonly class ListInspection
{
    /**
     * @param array<int, list<FilterIssue>> $rows The issues of each filter row, by its tl_flare_filter id;
     *   unpublished rows included.
     * @param array<string, int> $counts The number of filters with an issue, by {@see FilterIssueKind} value:
     *   published rows and filters without a row (added by the list driver or a listener) only.
     * @param list<string> $blockingAliases The aliases shared by two or more published rows.
     */
    public function __construct(
        public array $rows = [],
        public array $counts = [],
        public array $blockingAliases = [],
    ) {}

    /**
     * @return list<FilterIssue>
     */
    public function forRow(int $id): array
    {
        return $this->rows[$id] ?? [];
    }

    public function count(FilterIssueKind $kind): int
    {
        return $this->counts[$kind->value] ?? 0;
    }
}
