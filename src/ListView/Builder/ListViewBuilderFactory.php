<?php

namespace HeimrichHannot\FlareBundle\ListView\Builder;

use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;

readonly class ListViewBuilderFactory
{
    public function __construct(
        private ListViewResolverInterface $resolver,
    ) {}

    public function create(): ListViewBuilder
    {
        return new ListViewBuilder($this->resolver);
    }
}