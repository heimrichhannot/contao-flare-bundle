<?php

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;
use HeimrichHannot\FlareBundle\View\InteractiveView;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @todo(@ericges): Remove in 0.1.0
 * @deprecated Use {@see InteractiveView} instead.
 */
readonly class ListViewBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function create(): ListViewBuilder
    {
        return new ListViewBuilder(
            eventDispatcher: $this->dispatcher,
        );
    }
}