<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FilterPredicateRegistry
{
    /**
     * @var array<class-string<PredicateInterface>, PredicateInterface>
     */
    private array $types;

    public function __construct(
        #[TaggedIterator(PredicateInterface::FLARE_FILTER_PREDICATE_TAG)]
        private readonly iterable $filterTypes,
    ) {}

    /**
     * @param class-string<PredicateInterface> $class
     */
    public function get(string $class): ?PredicateInterface
    {
        return $this->resolve()[$class] ?? null;
    }

    /**
     * @return array<class-string<PredicateInterface>, PredicateInterface>
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
                if (!$filterType instanceof PredicateInterface) {
                    throw new \LogicException(\sprintf(
                        'Service "%s" is tagged "%s" but does not implement %s.',
                        $filterType::class,
                        PredicateInterface::FLARE_FILTER_PREDICATE_TAG,
                        PredicateInterface::class,
                    ));
                }

                $this->types[$filterType::class] = $filterType;
            }
        }

        return $this->types;
    }
}
