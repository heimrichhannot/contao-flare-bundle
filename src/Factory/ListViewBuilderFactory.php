<?php

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\Event\ListViewCreateBuilderEvent;
use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListViewBuilderFactory
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ListViewResolver         $resolver,
    ) {}

    public function create(): ListViewBuilder
    {
        $event = new ListViewCreateBuilderEvent(defaultResolver: $this->resolver);
        $event = $this->dispatcher->dispatch(event: $event, eventName: $event->getEventName());

        return new ListViewBuilder(
            eventDispatcher: $this->dispatcher,
            listViewResolver: $event->getResolver(),
        );
    }
}