<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;

interface FilterElementInterface
{
    /**
     * Declares the filter's form fields on the collect-only per-filter builder.
     *
     * Single-field elements declare their field via {@see FilterFormBuilderInterface::single()};
     * it is mounted flat on the root form under the filter's alias, and its value reaches
     * buildFilter() under {@see FilterContext::SINGLE_VALUE}. Multi-field elements add()
     * children with local names, which mount as a compound sub-form. Pre-submission defaults
     * belong in the fields' native `data` option. Event listeners registered on the builder are
     * replayed onto the mounted form; event subscribers are not supported. Declaring no fields
     * means the filter has no form representation.
     */
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    /**
     * Translates canonical config and runtime data into filter type calls.
     *
     * @param array<string, mixed> $values Submitted form data of this filter (keyed by the local
     *   field names declared in buildForm(); single() fields use {@see FilterContext::SINGLE_VALUE})
     *   or a programmatically set data bag; empty array when neither exists (e.g. non-interactive
     *   contexts).
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void;
}
