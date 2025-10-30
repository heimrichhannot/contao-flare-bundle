<?php

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\Event\CreateListViewBuilderEvent;
use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListViewBuilderFactory
{
    public function __construct(
        private ListViewResolver $resolver,
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function create(): ListViewBuilder
    {
        $event = $this->dispatcher->dispatch(new CreateListViewBuilderEvent());

        return new ListViewBuilder($event->getResolver($this->resolver));
    }
}