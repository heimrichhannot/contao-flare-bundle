<?php

namespace HeimrichHannot\FlareBundle\FlareContainer;

readonly class FlareContainerBuilderFactory
{
    public function __construct(
        private FlareContainerStrategies $containerStrategies,
    ) {}

    public function create(): FlareContainerBuilder
    {
        return new FlareContainerBuilder($this->containerStrategies);
    }
}