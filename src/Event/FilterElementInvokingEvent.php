<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\FilterInvokerInterface;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokingEvent extends Event
{
    /**
     * @param FilterInvocation $invocation
     * @param ContextInterface $context
     * @param FilterInvokerInterface $invoker The callback to invoke.
     * @param bool $shouldInvoke Whether the filter should be invoked.
     */
    public function __construct(
        private readonly FilterInvocation $invocation,
        private readonly ContextInterface $context,
        private FilterInvokerInterface    $invoker,
        private bool                      $shouldInvoke,
    ) {}

    public function getInvocation(): FilterInvocation
    {
        return $this->invocation;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getInvoker(): FilterInvokerInterface
    {
        return $this->invoker;
    }

    public function setInvoker(FilterInvokerInterface $invoker): void
    {
        $this->invoker = $invoker;
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