<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokingEvent extends Event
{
    public function __construct(
        private readonly ListDefinition   $listDefinition,
        private readonly FilterDefinition $filterDefinition,
        private readonly ContentContext   $contentContext,
        private \Closure                  $callback,
        private bool                      $shouldInvoke,
    ) {}

    public function getListDefinition(): ListDefinition
    {
        return $this->listDefinition;
    }

    public function getFilterDefinition(): FilterDefinition
    {
        return $this->filterDefinition;
    }

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
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