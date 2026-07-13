<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * Invocation context handed to filter elements, both when building the form
 * and when building the filter query.
 */
final readonly class FilterContext
{
    /** Attribute-bag key under which this context is stored on the per-filter form builder. */
    public const FORM_ATTRIBUTE = 'flare.filter_context';

    /** Conventional local child name for single-field filter elements. */
    public const FIELD_VALUE = 'value';

    /**
     * @param array<string, mixed> $config Resolved canonical config of the filter.
     * @param string|int|null $key Key of the filter within {@see ListSpecification::getFilters()}.
     */
    public function __construct(
        public ListSpecification $list,
        public Filter            $filter,
        public array             $config,
        public ContextInterface  $engineContext,
        public string|int|null   $key = null,
    ) {}
}
