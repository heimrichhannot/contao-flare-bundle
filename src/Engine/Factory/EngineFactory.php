<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Factory;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Registry\EngineModRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;

final readonly class EngineFactory
{
    public function __construct(
        private EngineModRegistry $engineModRegistry,
        private ProjectorRegistry $projectorRegistry,
    ) {}

    public function createEngine(IntentInterface $intent, ListCompound $list, array $mods = []): Engine
    {
        return new Engine(
            engineModRegistry: $this->engineModRegistry,
            projectorRegistry: $this->projectorRegistry,
            intent: $intent,
            list: $list,
            mods: $mods,
        );
    }
}
