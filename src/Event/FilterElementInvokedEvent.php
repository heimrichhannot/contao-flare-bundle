<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterElementConfig;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokedEvent extends Event
{
    public function __construct(
        private readonly FilterElementConfig $filterElementConfig,
        private readonly FilterQueryBuilder  $queryBuilder,
        private readonly FilterContext       $context,
        private readonly string              $method,
    ) {}

    public function getFilterElementConfig(): FilterElementConfig
    {
        return $this->filterElementConfig;
    }

    public function getFilterElement(): string
    {
        return $this->filterElementConfig->getService();
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