<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokingEvent extends Event
{
    public function __construct(
        private readonly FilterContext $filter,
        private \Closure               $callback,
        private bool                   $shouldInvoke,
    ) {}

    public function getFilter(): FilterContext
    {
        return $this->filter;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    public function shouldInvoke(): bool
    {
        return $this->shouldInvoke;
    }

    public function setShouldInvoke(bool $shouldInvoke): void
    {
        $this->shouldInvoke = $shouldInvoke;
    }
}