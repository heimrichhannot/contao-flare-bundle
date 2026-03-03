<?php

namespace HeimrichHannot\FlareBundle\Engine\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class EngineFactory
{
    public function __construct(
        private ProjectorRegistry $projectorRegistry,
    ) {}

    public function createEngine(
        ContextInterface $context,
        ListSpecification $listSpecification,
    ): Engine {
        return new Engine(
            context: $context,
            listSpecification: $listSpecification,
            projectorRegistry: $this->projectorRegistry,
        );
    }
}