<?php

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;
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