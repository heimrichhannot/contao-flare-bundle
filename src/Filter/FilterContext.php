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

    /**
     * Canonical values-bag key under which a single-field filter's value reaches buildFilter(),
     * regardless of whether the field was mounted flat or inside a compound filter form.
     */
    public const SINGLE_VALUE = '0';

    /**
     * @param array<string, mixed> $config Resolved canonical config of the filter.
     * @param string|int|null $key Key of the filter within {@see ListSpec::$filters}.
     */
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,
        public array            $config,
        public ContextInterface $engineContext,
        public string|int|null  $key = null,
    ) {}
}
