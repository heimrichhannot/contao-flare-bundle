<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ProjectionInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class Projectors
{
    private array $projectors;

    public function __construct(
        #[TaggedIterator('flare.projector', defaultIndexMethod: 'getContext')]
        private readonly iterable $contextIterable,
        #[TaggedIterator('flare.projector', defaultIndexMethod: 'getProjectionClass')]
        private readonly iterable $projectionIterable,
    ) {}

    private function resolve(): array
    {
        if (!isset($this->projectors))
        {
            foreach ($this->contextIterable as $type => $service)
            {
                $this->projectors[$type] = $service;
            }

            foreach ($this->projectionIterable as $class => $service)
            {
                $this->projectors[$class] = $service;
            }
        }

        return $this->projectors;
    }

    public function all(): iterable
    {
        return $this->resolve();
    }

    /**
     * @template T of ProjectionInterface
     * @param string|class-string<T> $type
     * @return ProjectorInterface<T>|null
     */
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

    /**
     * @template T of ProjectionInterface
     * @param class-string<T> $projectionClass
     * @return T|null
     */
    public function project(
        string         $projectionClass,
        ListContext    $listContext,
        ListDefinition $listDefinition,
    ): ?ProjectionInterface {
        $projection = $this->get($projectionClass)?->project($listContext, $listDefinition);

        if (!$projection instanceof $projectionClass) {
            return null;
        }

        return $projection;
    }
}