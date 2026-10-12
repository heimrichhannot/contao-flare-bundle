<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * Invocation context handed to filter elements, both when building the form
 * and when building the filter query.
 */
final readonly class FilterContext
{
    /** Attribute-bag key under which this context is stored on the per-filter form builder. */
    public const ATTR_SELF = 'flare.filter_context';

    /** Attribute-bag key marking a root form child as a flat-mounted single field. */
    public const ATTR_SINGLE_FIELD = 'flare.single_field';

    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,
        public Formula          $formula,
        public ContextInterface $engineContext,
    ) {}
}
