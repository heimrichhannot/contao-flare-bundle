<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;

#[AsFilterElement(alias: CalendarCurrentElement::TYPE)]
class CalendarCurrentElement
{
    public const TYPE = 'flare_calendar_current';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();

        $start = 0;
        $end = \min(\PHP_INT_MAX, 4294967295);

        $qb->where($qb->expr()->or(
            "startTime>=:start AND startTime<=:end",  // event starts in range
            "endTime>=:start AND endTime<=:end",  // event ends in range
            "startTime<=:start AND endTime>=:end",  // event is in range
            $qb->expr()->and(  // event is recurring
                "recurring=1",
                $qb->expr()->or("recurrences=0", "repeatEnd>=:start"),
                "startTime<=:end",  // event starts before end of range
            ),
        ));

        $qb->bind('start', $start);
        $qb->bind('end', $end);
    }
}