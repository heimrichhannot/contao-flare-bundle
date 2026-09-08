<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Filter\LogicSequencerInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;

interface FilterElementInterface
{
    /**
     * Declares the filter's form fields on the collect-only per-filter builder.
     *
     * Single-field elements declare their field via {@see FilterFormBuilderInterface::single()};
     * it is mounted flat on the root form under the filter's alias, and its value reaches
     * buildFilter() as {@see FilterData::getSingleValue()}. Multi-field elements add()
     * children with local names, which mount as a compound sub-form. Declaring both at once is
     * not supported and fails when the form is built. Pre-submission defaults
     * belong in the fields' native `data` option. Event listeners registered on the builder are
     * replayed onto the mounted form; event subscribers are not supported. Declaring no fields
     * means the filter has no form representation.
     */
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    /**
     * Translates canonical config and runtime data into filter type calls.
     *
     * @param FilterData $data Submitted form data of this filter — {@see FilterData::get()} by
     *   the local field names declared in buildForm(), or {@see FilterData::getSingleValue()}
     *   for a single() field — or the programmatically set {@see \HeimrichHannot\FlareBundle\Filter\Filter::$data};
     *   {@see FilterData::none()} when neither exists (e.g. non-interactive contexts).
     */
    public function buildLogic(LogicSequencerInterface $builder, FilterContext $context, FilterData $data): void;
}
