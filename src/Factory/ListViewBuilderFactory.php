<?php

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\ListView\ListViewBuilder;
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