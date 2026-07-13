<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a filter element built its form children on the per-filter compound
 * sub-builder, before the sub-builder is mounted onto the root filter form.
 *
 * Listeners may add, remove, or replace children (re-adding a child with the same name
 * overwrites it) or cancel mounting altogether.
 */
class FilterElementFormBuiltEvent extends Event
{
    public function __construct(
        private readonly FormBuilderInterface $builder,
        private readonly FilterContext        $context,
        private bool                          $cancelled = false,
    ) {}

    public function getBuilder(): FormBuilderInterface
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
