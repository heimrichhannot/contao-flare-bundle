<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterElementConfig;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use Symfony\Contracts\EventDispatcher\Event;

class FilterQueryBuilderEvent extends Event
{
    public function __construct(
        private readonly FilterElementConfig $filterElementConfig,
        private readonly string              $method,
        private readonly FilterQueryBuilder  $queryBuilder,
        private readonly FilterContext       $context,
    ) {}

    public function getFilterElementConfig(): FilterElementConfig
    {
        return $this->filterElementConfig;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getQueryBuilder(): FilterQueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getContext(): FilterContext
    {
        return $this->context;
    }
}