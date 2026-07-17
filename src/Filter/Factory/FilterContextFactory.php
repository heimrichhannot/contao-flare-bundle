<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * Builds the invocation context handed to filter elements, resolving the filter's
 * canonical config through the element's declared schema.
 */
final readonly class FilterContextFactory
{
    public function __construct(
        private FilterOptionsResolver $filterOptionsResolver,
    ) {}

    /**
     * @throws FilterException If the filter's config violates the element's schema
     */
    public function create(
        ListSpec      $list,
        Filter                 $filter,
        FilterElementInterface $element,
        ContextInterface       $engineContext,
        string|int|null        $key = null,
    ): FilterContext {
        return new FilterContext(
            list: $list,
            filter: $filter,
            config: $this->filterOptionsResolver->resolve($filter, $element),
            engineContext: $engineContext,
            key: $key,
        );
    }
}
