<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

interface ListItemProviderInterface
{
    public function fetchCount(FilterContextCollection $filters): int;

    public function fetchEntries(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array;

    public function fetchIds(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array;
}