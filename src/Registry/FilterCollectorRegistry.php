<?php

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\FilterCollector\FilterCollectorInterface;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FilterCollectorRegistry
{
    private array $collectors;

    /**
     * @param iterable<FilterCollectorInterface> $collectorsIterable
     */
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

    public function match(ListDataSourceInterface $dataSource): ?FilterCollectorInterface
    {
        /** @var FilterCollectorInterface $collector */
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