<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Collector;

use HeimrichHannot\FlareBundle\Filter\Filter;

/**
 * The filters collected for a list, and the failures of filters that fail the list as a whole
 * (SPEC_FILTER_FORMS.md §8.1).
 */
final readonly class CollectedFilters
{
    /**
     * @param array<string, Filter> $filters Keyed by alias; aliases are unique.
     * @param list<string> $failures Failure messages, each naming its source.
     */
    public function __construct(
        public array $filters = [],
        public array $failures = [],
    ) {}
}
