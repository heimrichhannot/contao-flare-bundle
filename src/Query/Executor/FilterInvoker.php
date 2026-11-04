<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Executor;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterEffectFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use HeimrichHannot\FlareBundle\Util\CreatesFilterExceptionTrait;

/**
 * Applies one filter with one value: the element's predicate calls, each built into a conditions builder. Shared by
 * the {@see ListQueryDirector} and the backend's check for broken filters.
 *
 * @internal
 */
final readonly class FilterInvoker
{
    use CreatesFilterExceptionTrait;

    public function __construct(
        private FilterEffectFactory       $filterEffectFactory,
        private FilterQueryBuilderFactory $filterQueryBuilderFactory,
    ) {}

    /**
     * Builds the filter's effect with the given value and runs each of its predicate calls into a conditions
     * builder.
     *
     * @return list<FilterConditionsBuilder>
     *
     * @throws AbortFilteringException
     * @throws FilterException Wrapped with the filter's source.
     * @throws FlareException
     */
    public function invoke(ElementContext $context, ?ValueInterface $value): array
    {
        $filter = $context->filter;

        try
        {
            $effect = $this->filterEffectFactory->create($context, $value);
        }
        catch (\Exception $e)
        {
            throw $this->wrapFilterFailure($e, $filter, $filter->element->component::class . '::buildEffect');
        }

        $filterQueryBuilders = [];

        foreach ($effect->formula->propositions as $proposition)
        // todo: this should be simplified, either FilterConditionsBuilderFactory like FilterEffectFactory
        //   or skip collecting the builders and build right away
        {
            $filterQueryBuilder = $this->filterQueryBuilderFactory->create($proposition->targetAlias);

            try
            {
                $proposition->predicate->buildConditions($filterQueryBuilder, $proposition->options);
            }
            catch (\Exception $e)
            {
                throw $this->wrapFilterFailure($e, $filter, $proposition->predicateClass . '::buildConditions');
            }

            $filterQueryBuilders[] = $filterQueryBuilder;
        }

        return $filterQueryBuilders;
    }

    /**
     * The exception to rethrow for a failure while applying a filter: a {@see FilterException} is
     * wrapped with the filter's source, any other {@see FlareException} (incl. aborting) passes through
     * unchanged, and other exceptions become a {@see FilterException}. Errors (`\Error`) are programming
     * errors and are never caught.
     */
    private function wrapFilterFailure(\Exception $e, Filter $filter, string $method): FlareException
    {
        return match (true) {
            $e instanceof FilterException => $this->createFilterException($e, $filter, $method),
            $e instanceof FlareException => $e,
            default => FilterException::wrap($e, $filter, $method),
        };
    }
}
