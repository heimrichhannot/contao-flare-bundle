<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;

#[AsFilterElement(
    alias: DateRangeElement::TYPE,
    palette: 'fieldGeneric',
    formType: DateRangeFilterType::class,
)]
class DateRangeElement extends AbstractFilterElement
{
    public const TYPE = 'flare_dateRange';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedData = $context->getSubmittedData();
        $filterModel = $context->getFilterModel();

        if (!$field = $filterModel->fieldGeneric) {
            throw new FilterException('Set fieldGeneric in filter model.');
        }

        $from = $submittedData['from'] ?? null;
        $to = $submittedData['to'] ?? null;

        $colField = $qb->column($field);

        if ($from instanceof \DateTimeInterface) {
            $qb->where($qb->expr()->gte($colField, ':from'))
                ->setParameter('from', $from->getTimestamp());
        }

        if ($to instanceof \DateTimeInterface) {
            $qb->where($qb->expr()->lte($colField, ':to'))
                ->setParameter('to', $to->getTimestamp());
        }
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        return [
            'required' => false,
        ];
    }
}