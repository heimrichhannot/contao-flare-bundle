<?php

namespace HeimrichHannot\FlareBundle\Filter\Collector;

use HeimrichHannot\FlareBundle\List\ListDataSource;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FilterCollectors
{
    private array $collectors;

    public function __construct(
        #[TaggedIterator('flare.filter_collector')]
        private readonly iterable $collectorsIterable,
    ) {}

    private function resolve(): array
    {
        if (!isset($this->collectors))
        {
            $this->collectors = \iterator_to_array($this->collectorsIterable);
        }

        return $this->collectors;
    }

    public function all(): iterable
    {
        return $this->resolve();
    }

    public function match(ListDataSource $dataSource): ?FilterCollectorInterface
    {
        foreach ($this->resolve() as $collector)
        {
            if ($collector->supports($dataSource))
            {
                return $collector;
            }
        }

        return null;
    }
}