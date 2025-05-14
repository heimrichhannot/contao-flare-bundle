<?php

namespace HeimrichHannot\FlareBundle\List\Type\ItemProvider;

use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

class EventsListItemProvider implements ListItemProviderInterface
{

    public function fetchCount(FilterContextCollection $filters): int
    {
        // TODO: Implement fetchCount() method.

        return 0;
    }

    public function fetchEntries(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        // TODO: Implement fetchEntries() method.

        return [];
    }

    public function fetchIds(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        // TODO: Implement fetchIds() method.

        return [];
    }
}