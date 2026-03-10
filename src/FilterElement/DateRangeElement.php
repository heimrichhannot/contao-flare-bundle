<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;

#[AsFilterElement(
    type: self::TYPE,
    palette: 'fieldGeneric',
    formType: DateRangeFilterType::class,
)]
class DateRangeElement extends AbstractFilterElement
{
    public const TYPE = 'flare_dateRange';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $value = $inv->getValue();

        if (!$field = $inv->filter->fieldGeneric) {
            throw new FilterException('Set fieldGeneric in filter model.');
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

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

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['required'] = false;
    }
}