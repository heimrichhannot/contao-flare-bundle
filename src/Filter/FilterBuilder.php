<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Type\FilterTypeInterface;
use HeimrichHannot\FlareBundle\Registry\FilterTypeRegistry;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterBuilder implements FilterBuilderInterface
{
    /**
     * @var array<class-string<FilterTypeInterface>, OptionsResolver>
     */
    private static array $optionsResolvers = [];

    /**
     * @var FilterCall[]
     */
    private array $calls = [];

    public function __construct(
        private readonly FilterTypeRegistry $filterTypeRegistry,
        private readonly string             $defaultTargetAlias,
    ) {}

    /**
     * @param class-string<FilterTypeInterface> $type
     * @param array<string, mixed> $options
     *
     * @throws FilterException
     */
    public function add(string $type, array $options = [], ?string $targetAlias = null): static
    {
        if (!$filterType = $this->filterTypeRegistry->get($type)) {
            throw new FilterException(\sprintf('No FLARE filter type service registered for "%s".', $type));
        }

        if (!isset(self::$optionsResolvers[$type]))
        {
            $resolver = new OptionsResolver();
            $filterType->configureOptions($resolver);
            self::$optionsResolvers[$type] = $resolver;
        }

        $this->calls[] = new FilterCall(
            type: $filterType,
            typeClass: $type,
            targetAlias: $targetAlias ?: $this->defaultTargetAlias,
            options: self::$optionsResolvers[$type]->resolve($options),
        );

        return $this;
    }

    public function all(): array
    {
        return $this->calls;
    }

    public function abort(): never
    {
        throw new AbortFilteringException();
    }
}
