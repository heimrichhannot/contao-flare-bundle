<?php

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Engine\Mod\ModInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EngineModRegistry
{
    private array $resolved;

    public function __construct(
        #[TaggedIterator('flare.engine_mod', defaultIndexMethod: 'getType')]
        private readonly iterable $mods,
    ) {}

    public function resolve(): array
    {
        return $this->resolved ??= \iterator_to_array($this->mods);
    }

    public function get(string $type): ?ModInterface
    {
        return $this->resolve()[$type] ?? null;
    }
}