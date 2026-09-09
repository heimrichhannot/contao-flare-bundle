<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;
use HeimrichHannot\FlareBundle\Registry\FilterPredicateRegistry;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FormulaBuilder implements FormulaBuilderInterface
{
    /**
     * @var array<class-string<PredicateInterface>, OptionsResolver>
     */
    private static array $optionsResolvers = [];

    /**
     * @var Proposition[]
     */
    private array $propositions = [];

    public function __construct(
        private readonly FilterPredicateRegistry $filterPredicateRegistry,
        private readonly string                  $defaultTargetAlias,
    ) {}

    /**
     * @param class-string<PredicateInterface> $predicateClass
     * @param array<string, mixed> $options
     *
     * @throws FilterException
     */
    public function add(string $predicateClass, array $options = [], ?string $targetAlias = null): static
    {
        if (!$predicate = $this->filterPredicateRegistry->get($predicateClass))
        {
            throw new FilterException(
                \sprintf('No FLARE filter predicate service registered for "%s".', $predicateClass),
                method: __METHOD__,
            );
        }

        if (!isset(self::$optionsResolvers[$predicateClass]))
        {
            $resolver = new OptionsResolver();
            $predicate->configureOptions($resolver);
            self::$optionsResolvers[$predicateClass] = $resolver;
        }

        $this->propositions[] = new Proposition(
            predicate: $predicate,
            predicateClass: $predicateClass,
            targetAlias: $targetAlias ?: $this->defaultTargetAlias,
            options: self::$optionsResolvers[$predicateClass]->resolve($options),
        );

        return $this;
    }

    public function abort(): never
    {
        throw new AbortFilteringException();
    }

    public function build(): Formula
    {
        return new Formula($this->propositions);
    }
}
