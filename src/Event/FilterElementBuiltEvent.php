<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementBuiltEvent extends Event
{
    public function __construct(
        private readonly FilterInvocation       $invocation,
        private readonly FilterBuilderInterface $builder,
    ) {}

    public function getInvocation(): FilterInvocation
    {
        return $this->invocation;
    }

    public function getBuilder(): FilterBuilderInterface
    {
        return $this->builder;
    }
}