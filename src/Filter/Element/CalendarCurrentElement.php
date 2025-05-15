<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;

#[AsFilterElement(
    alias: CalendarCurrentElement::TYPE,
    formType: DateRangeFilterType::class,
)]
class CalendarCurrentElement implements PaletteContract
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

    public function getPalette(PaletteConfig $config): ?string
    {
        return '{date_start_legend},configureStart;{date_stop_legend},configureStop';
    }
}