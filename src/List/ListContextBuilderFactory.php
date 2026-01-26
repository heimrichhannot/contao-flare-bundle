<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Projector\Projectors;

readonly class ListContextBuilderFactory
{
    public function __construct(
        private Projectors $projectors,
    ) {}

    public function create(): ListContextBuilder
    {
        return new ListContextBuilder(projectors: $this->projectors);
    }
}