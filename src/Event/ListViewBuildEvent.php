<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;

class ListViewBuildEvent extends AbstractFlareEvent
{
    public function __construct(
        private readonly ListViewBuilder $builder,
    ) {}

    public function getBuilder(): ListViewBuilder
    {
        return $this->builder;
    }

    public function getEventName(): string
    {
        return 'flare.list_view.build';
    }
}