<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Registry\EngineModRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class EngineFactory
{
    public function __construct(
        private EngineModRegistry $engineModRegistry,
        private ProjectorRegistry $projectorRegistry,
    ) {}

    public function createEngine(
        ContextInterface $context,
        ListSpecification $listSpecification,
        array $mods = [],
    ): Engine {
        return new Engine(
            context: $context,
            list: $listSpecification,
            mods: $mods,
            engineModRegistry: $this->engineModRegistry,
            projectorRegistry: $this->projectorRegistry,
        );
    }
}