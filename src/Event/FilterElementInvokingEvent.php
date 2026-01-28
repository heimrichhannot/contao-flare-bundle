<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokingEvent extends Event
{
    public function __construct(
        private readonly ListSpecification      $listSpecification,
        private readonly FilterDefinition       $filterDefinition,
        private readonly ContextConfigInterface $contextConfig,
        private \Closure                        $callback,
        private bool                            $shouldInvoke,
    ) {}

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    public function getFilterDefinition(): FilterDefinition
    {
        return $this->filterDefinition;
    }

    public function getContextConfig(): ContextConfigInterface
    {
        return $this->contextConfig;
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