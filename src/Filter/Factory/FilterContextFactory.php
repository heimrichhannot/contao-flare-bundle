<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Util\Str;

/**
 * Builds the {@see FilterContext} of one invocation: applies the element to the scope and the value.
 */
final readonly class FilterContextFactory
{
    public function __construct(
        private FilterContextBuilderFactory $filterContextBuilderFactory,
    ) {}

    /**
     * @throws FilterException If the filter's config violates the element's schema
     * @throws FlareException
     */
    public function create(ElementScope $scope, ?ValueInterface $value): FilterContext
    {
        if (!Str::isValidSqlName($table = $scope->list->dc))
        {
            throw new FlareException(\sprintf(
                '[FLARE] ListSpec data container cannot be used as SQL table identifier: "%s"',
                $table
            ), method: __METHOD__, source: $scope->filter->source ?: 'filter inlined');
        }

        $contextBuilder = $this->filterContextBuilderFactory->create($scope);

        $scope->filter->element->component->buildContext($contextBuilder, $scope, $value);

        return $contextBuilder->build();
    }
}
