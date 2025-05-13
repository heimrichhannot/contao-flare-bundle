<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;
use Safe\Exceptions\FilterException;

#[AsFilterElement(
    alias: DateRangeElement::TYPE,
    palette: 'fieldGeneric',
    formType: DateRangeFilterType::class,
)]
class DateRangeElement implements FormTypeOptionsContract
{
    public const TYPE = 'flare_dateRange';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedData = $context->getSubmittedData();
        $filterModel = $context->getFilterModel();

        if (!$field = $filterModel?->fieldGeneric) {
            throw new FilterException('Set fieldGeneric in filter model.');
        }

        $from = $submittedData['from'] ?? null;
        $to = $submittedData['to'] ?? null;

        if ($from instanceof \DateTimeInterface) {
            $qb->where($qb->expr()->gte($field, ':from'))
                ->bind('from', $from->getTimestamp());
        }

        if ($to instanceof \DateTimeInterface) {
            $qb->where($qb->expr()->lte($field, ':to'))
                ->bind('to', $to->getTimestamp());
        }
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        return [
            'required' => false,
        ];
    }
}