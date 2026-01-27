<?php

namespace HeimrichHannot\FlareBundle\Projector;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class Projectors
{
    private array $projectors;

    public function __construct(
        #[TaggedIterator('flare.projector', defaultIndexMethod: 'getContext')]
        private readonly iterable $projectorsIterable,
    ) {}

    private function resolve(): array
    {
        if (!isset($this->projectors))
        {
            foreach ($this->projectorsIterable as $type => $service)
            {
                $this->projectors[$type] = $service;
            }
        }

        return $this->projectors;
    }

    public function all(): iterable
    {
        return $this->resolve();
    }

    public function get(string $type): ?ProjectorInterface
    {
        if (!$type) {
            return null;
        }

        return $this->resolve()[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->resolve()[$type]);
    }

    public function types(): array
    {
        return \array_keys($this->resolve());
    }
}