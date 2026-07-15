<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a filter element built its fields on the collect-only per-filter builder,
 * before the factory mounts them onto the root filter form (flat for single() fields without
 * companions, nested compound otherwise).
 *
 * Listeners may add, remove, or replace children (re-adding a child with the same name
 * overwrites it), adjust the single-field declaration via {@see FilterFormBuilderInterface::single()},
 * or cancel mounting altogether. Adding a child alongside a single() declaration switches the
 * filter to the nested compound layout.
 */
class FilterElementFormBuiltEvent extends Event
{
    public function __construct(
        private readonly FilterFormBuilderInterface $builder,
        private readonly FilterContext              $context,
        private bool                                $cancelled = false,
    ) {}

    public function getBuilder(): FilterFormBuilderInterface
    {
        return $this->builder;
    }

    public function getContext(): FilterContext
    {
        return $this->context;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
