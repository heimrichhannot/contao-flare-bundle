<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer;

use HeimrichHannot\FlareBundle\List\FilterFailure;

/**
 * The filters of a list that are guaranteed to break it ({@see BrokenFilterInspector}).
 *
 * @internal
 */
final readonly class FilterDiagnosis
{
    /**
     * @param array<int, FilterFailure> $rows The failure of each broken filter row, by its tl_flare_filter id;
     *   unpublished rows included.
     * @param list<FilterFailure> $listFailures The failures that fail the list: of its published and inline filters.
     */
    public function __construct(
        public array $rows = [],
        public array $listFailures = [],
    ) {}

    public function forRow(int $id): ?FilterFailure
    {
        return $this->rows[$id] ?? null;
    }
}
