<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Type\DateRangeFilterType as DateRangeQueryFilterType;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;

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
    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void
    {
        $value = (array) ($invocation->getValue() ?: []);

        if (!$field = $invocation->filter->fieldGeneric) {
            throw new FilterException('Set fieldGeneric in filter model.');
        }

        $builder->add(DateRangeQueryFilterType::class, [
            'field' => $field,
            'from' => $value['from'] ?? null,
            'to' => $value['to'] ?? null,
        ]);
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['required'] = false;
    }
}
