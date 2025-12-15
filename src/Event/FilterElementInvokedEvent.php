<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokedEvent extends Event
{
    public function __construct(
        private readonly FilterContext      $filter,
        private readonly FilterQueryBuilder $queryBuilder,
        private readonly string             $method,
    ) {}

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getQueryBuilder(): FilterQueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getFilter(): FilterContext
    {
        return $this->filter;
    }
}