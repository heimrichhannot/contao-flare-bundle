<?php

namespace HeimrichHannot\FlareBundle\ListView\Builder;

use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolver;

readonly class ListViewBuilderFactory
{
    public function __construct(
        private ListViewResolver $containerStrategies,
    ) {}

    public function create(): ListViewBuilder
    {
        return new ListViewBuilder($this->containerStrategies);
    }
}