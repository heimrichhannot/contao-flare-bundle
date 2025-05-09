<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;

#[AsFilterElement(
    alias: DateRangeElement::TYPE,
    palette: 'fieldGeneric',
    formType: DateRangeFilterType::class,
)]
class DateRangeElement implements FormTypeOptionsContract
{
    public const TYPE = 'flare_dateRange';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        // TODO: Implement __invoke() method.
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        return [
            'required' => false,
        ];
    }
}