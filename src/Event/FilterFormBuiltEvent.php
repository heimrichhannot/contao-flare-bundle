<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a filter element built its fields on the collect-only per-filter builder,
 * before {@see \HeimrichHannot\FlareBundle\Filter\Factory\FormHarnessFactory} mounts them onto the
 * root form (flat for single() fields without companions, nested compound otherwise).
 *
 * Listeners may add, remove, or replace children (re-adding a child with the same name
 * overwrites it), adjust the single-field declaration via {@see FilterFormBuilderInterface::single()},
 * or cancel mounting altogether. Adding a child alongside a single() declaration switches the
 * filter to the nested compound layout.
 *
 * Also dispatched under the name `flare.filter_form.{type}.built`
 * ({@see \HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FilterFormListener}).
 */
class FilterFormBuiltEvent extends Event
{
    public function __construct(
        public readonly FilterFormBuilderInterface $builder,
        public readonly FilterContext              $context,
        private bool                               $cancelled = false,
    ) {}

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
