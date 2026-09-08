<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Util\CreatesFilterExceptionTrait;
use HeimrichHannot\FlareBundle\Util\Str;

/**
 * Builds the invocation context handed to filter elements, resolving the filter's
 * canonical config through the element's declared schema.
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
    public function create(ListSpec $list, Filter $filter, ContextInterface $engineContext): FilterContext
    {
        if (!Str::isValidSqlName($table = $list->dc))
        {
            throw new FlareException(\sprintf(
                '[FLARE] ListSpec data container cannot be used as SQL table identifier: "%s"',
                $table
            ), method: __METHOD__, source: $filter->source ?: 'filter inlined');
        }

        $contextBuilder = $this->filterContextBuilderFactory->create($list, $filter, $engineContext);

        // $data = $options->filterValues[$key] ?? $filter->data ?? FilterData::none();
        // todo: pass filter data to buildContext() so that elements can access it when building the filter

        $filter->element->buildContext($contextBuilder, null);

        return $contextBuilder->build();
    }
}
