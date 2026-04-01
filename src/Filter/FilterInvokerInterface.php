<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;

interface FilterInvokerInterface
{
    /**
     * @param FilterInvocation $inv The filter invocation data.
     * @param FilterQueryBuilder $qb The query builder to modify.
     * @return void
     * @throws FilterException If the filter causes an error deliberately.
     * @throws AbortFilteringException If the filter causes an empty result set (stops the filtering process early).
     * @throws \Throwable If the filter throws any unexpected error.
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void;
}