<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementInvokingEvent extends Event
{
    /**
     * @param FilterInvocation $invocation
     * @param \Closure|array $callback The callback to be invoked. Signature: (FilterInvocation, FilterQueryBuilder): void
     * @param bool $shouldInvoke Whether the filter should be invoked.
     */
    public function __construct(
        private readonly FilterInvocation $invocation,
        private \Closure|array            $callback,
        private bool                      $shouldInvoke,
    ) {
        if (!\is_callable($callback)) {
            throw new \InvalidArgumentException('The callback must be callable.');
        }
    }

    public function getInvocation(): FilterInvocation
    {
        return $this->invocation;
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