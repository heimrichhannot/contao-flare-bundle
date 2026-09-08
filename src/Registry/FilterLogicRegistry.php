<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Filter\Logic\LogicInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FilterLogicRegistry
{
    /**
     * @var array<class-string<LogicInterface>, LogicInterface>
     */
    private array $types;

    public function __construct(
        #[TaggedIterator(LogicInterface::FLARE_FILTER_LOGIC_TAG)]
        private readonly iterable $filterTypes,
    ) {}

    /**
     * @param class-string<LogicInterface> $class
     */
    public function get(string $class): ?LogicInterface
    {
        return $this->resolve()[$class] ?? null;
    }

    /**
     * @return array<class-string<LogicInterface>, LogicInterface>
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
                if (!$filterType instanceof LogicInterface) {
                    throw new \LogicException(\sprintf(
                        'Service "%s" is tagged "%s" but does not implement %s.',
                        $filterType::class,
                        LogicInterface::FLARE_FILTER_LOGIC_TAG,
                        LogicInterface::class,
                    ));
                }

                $this->types[$filterType::class] = $filterType;
            }
        }

        return $this->types;
    }
}
