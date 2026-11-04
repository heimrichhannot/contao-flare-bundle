<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterEffect;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Util\Str;

/**
 * Builds the {@see FilterEffect} of one invocation: applies the element to the context and the value.
 */
final readonly class FilterEffectFactory
{
    public function __construct(
        private FilterEffectBuilderFactory $filterEffectBuilderFactory,
    ) {}

    /**
     * @throws FilterException If the filter's config violates the element's schema
     * @throws FlareException
     */
    public function create(ElementContext $context, ?ValueInterface $value): FilterEffect
    {
        if (!Str::isValidSqlName($table = $context->list->dc))
        {
            throw new FlareException(\sprintf(
                '[FLARE] ListCompound data container cannot be used as SQL table identifier: "%s"',
                $table
            ), method: __METHOD__, source: $context->filter->source ?: 'filter inlined');
        }

        $effectBuilder = $this->filterEffectBuilderFactory->create($context);

        $context->filter->element->component->buildEffect($effectBuilder, $context, $value);

        return $effectBuilder->build();
    }
}
