<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Filter\Logic\FilterLogicInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FilterLogicRegistry
{
    /**
     * @var array<class-string<FilterLogicInterface>, FilterLogicInterface>
     */
    private array $types;

    public function __construct(
        #[TaggedIterator(FilterLogicInterface::FLARE_FILTER_LOGIC_TAG)]
        private readonly iterable $filterTypes,
    ) {}

    /**
     * @param class-string<FilterLogicInterface> $class
     */
    public function get(string $class): ?FilterLogicInterface
    {
        return $this->resolve()[$class] ?? null;
    }

    /**
     * @return array<class-string<FilterLogicInterface>, FilterLogicInterface>
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
                if (!$filterType instanceof FilterLogicInterface) {
                    throw new \LogicException(\sprintf(
                        'Service "%s" is tagged "%s" but does not implement %s.',
                        $filterType::class,
                        FilterLogicInterface::FLARE_FILTER_LOGIC_TAG,
                        FilterLogicInterface::class,
                    ));
                }

                $this->types[$filterType::class] = $filterType;
            }
        }

        return $this->types;
    }
}
