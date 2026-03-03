<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;
use Symfony\Contracts\EventDispatcher\Event;

class ListViewBuildEvent extends Event
{
    public function __construct(
        private readonly ListViewBuilder $builder,
    ) {}

    public function getBuilder(): ListViewBuilder
    {
        return $this->builder;
    }
}