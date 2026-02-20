<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokedEvent extends Event
{
    public function __construct(
        private readonly FilterInvocation   $invocation,
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

    public function getInvocation(): FilterInvocation
    {
        return $this->invocation;
    }
}