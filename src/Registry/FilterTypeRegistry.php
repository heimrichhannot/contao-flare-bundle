<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Filter\Type\FilterTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FilterTypeRegistry
{
    /**
     * @var array<class-string<FilterTypeInterface>, FilterTypeInterface>
     */
    private array $types;

    public function __construct(
        #[TaggedIterator(FilterTypeInterface::TAG)]
        private readonly iterable $filterTypes,
    ) {}

    /**
     * @param class-string<FilterTypeInterface> $class
     */
    public function get(string $class): ?FilterTypeInterface
    {
        return $this->resolve()[$class] ?? null;
    }

    /**
     * @return array<class-string<FilterTypeInterface>, FilterTypeInterface>
     */
    public function all(): array
    {
        return $this->resolve();
    }

    private function resolve(): array
    {
        if (!isset($this->types)) {
            $this->types = [];

            foreach ($this->filterTypes as $filterType) {
                if (!$filterType instanceof FilterTypeInterface) {
                    continue;
                }

                $this->types[$filterType::class] = $filterType;
            }
        }

        return $this->types;
    }
}