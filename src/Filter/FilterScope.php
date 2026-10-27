<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * The value-free config triple a filter is evaluated in: stage 1 of the two-stage filter
 * invocation. Everything that happens before a filter value exists receives the scope.
 */
final readonly class FilterScope
{
    /** Attribute-bag key under which this scope is stored on the per-filter form builder. */
    public const ATTR_SELF = 'flare.filter_scope';

    /** Attribute-bag key marking a root form child as a flat-mounted single field. */
    public const ATTR_SINGLE_FIELD = 'flare.single_field';

    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,
        public ContextInterface $engineContext,
    ) {}

    /**
     * The element's scope, to hand to element contracts.
     */
    public function forElement(): ElementScope
    {
        return new ElementScope($this->list, $this->filter, $this->engineContext);
    }
}
