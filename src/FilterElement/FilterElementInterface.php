<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Component\Form\FormBuilderInterface;

interface FilterElementInterface
{
    /**
     * Adds form children to the per-filter compound sub-builder.
     *
     * The element may add any number of children with local names ({@see FilterContext::FIELD_VALUE}
     * is the convention for single-field elements). Pre-submission defaults belong in the children's
     * native `data` option. Adding no children means the filter has no form representation.
     */
    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void;

    /**
     * Translates canonical config and runtime data into filter type calls.
     *
     * @param array<string, mixed> $data Submitted form data of this filter's compound child (keyed by
     *   the local child names added in buildForm()) or a programmatically set data bag; empty array
     *   when neither exists (e.g. non-interactive contexts).
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void;
}
