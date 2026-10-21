<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * A filter element describes the conditions of one filter. How the user supplies its value is
 * the concern of a {@see \HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface}.
 */
interface FilterElementInterface
{
    /**
     * Translates canonical config and the filter's value into filter type calls.
     *
     * @param ValueInterface|null $value A value of the element's value class
     *   ({@see \HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement::$value}), or null.
     */
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void;
}
