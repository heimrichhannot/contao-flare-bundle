<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Logic\LogicInterface;
use HeimrichHannot\FlareBundle\Registry\FilterLogicRegistry;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LogicSequencer implements LogicSequencerInterface
{
    /**
     * @var array<class-string<LogicInterface>, OptionsResolver>
     */
    private static array $optionsResolvers = [];

    /**
     * @var LogicStep[]
     */
    private array $steps = [];

    public function __construct(
        private readonly FilterLogicRegistry $filterTypeRegistry,
        private readonly string              $defaultTargetAlias,
    ) {}

    /**
     * @param class-string<LogicInterface> $type
     * @param array<string, mixed> $options
     *
     * @throws FilterException
     */
    public function add(string $type, array $options = [], ?string $targetAlias = null): static
    {
        if (!$filterType = $this->filterTypeRegistry->get($type))
        {
            throw new FilterException(
                \sprintf('No FLARE filter type service registered for "%s".', $type),
                method: __METHOD__,
            );
        }

        if (!isset(self::$optionsResolvers[$type]))
        {
            $resolver = new OptionsResolver();
            $filterType->configureOptions($resolver);
            self::$optionsResolvers[$type] = $resolver;
        }

        $this->steps[] = new LogicStep(
            type: $filterType,
            typeClass: $type,
            targetAlias: $targetAlias ?: $this->defaultTargetAlias,
            options: self::$optionsResolvers[$type]->resolve($options),
        );

        return $this;
    }

    public function all(): array
    {
        return $this->steps;
    }

    public function abort(): never
    {
        throw new AbortFilteringException();
    }
}
