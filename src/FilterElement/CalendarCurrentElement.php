<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsFilterElement(
    alias: CalendarCurrentElement::TYPE,
    formType: DateRangeFilterType::class,
)]
class CalendarCurrentElement implements FormTypeOptionsContract, PaletteContract
{
    public const TYPE = 'flare_calendar_current';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedData = $context->getSubmittedData();
        $filterModel = $context->getFilterModel();

        $start = \strtotime($filterModel->startAt) ?: 0;
        $stop = \strtotime($filterModel->stopAt) ?: DateTimeHelper::maxTimestamp();

        $from = $submittedData['from'] ?? null;
        $to = $submittedData['to'] ?? null;

        if ($from instanceof \DateTimeInterface)
        {
            $from = $from->getTimestamp();

            if (!$filterModel->isLimited || $from >= $start) {
                $start = $from;
            }
        }

        if ($to instanceof \DateTimeInterface)
        {
            $to = $to->getTimestamp();

            if (!$filterModel->isLimited || $to <= $stop) {
                $stop = $to;
            }
        }

        $or = [
            "startTime>=:start AND startTime<=:end",  // event starts in range
            $qb->expr()->and(  // event is recurring
                "recurring=1",
                $qb->expr()->or("recurrences=0", "repeatEnd>=:start"),
                "startTime<=:end",  // event starts before end of range
            ),
        ];

        if ($filterModel->hasExtendedEvents) {
            $or[] = "endTime>=:start AND endTime<=:end";  // event ends in range
            $or[] = "startTime<=:start AND endTime>=:end";  // event is in range
        }

        $qb->whereOr(...$or);

        $qb->setParameter('start', $start);
        $qb->setParameter('end', $stop);
    }

    #[AsEventListener('huh.flare.filter_element.' . self::TYPE . '.invoking')]
    public function onInvoking(FilterElementInvokingEvent $event): void
    {
        $filterModel = $event->getFilter()->getFilterModel();

        if (!$filterModel->isLimited && $event->getFilter()->getContentContext()->isReader()) {
            $event->setShouldInvoke(false);
        }
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $filterModel = $config->getFilterModel();

        $palette = '{date_start_legend},configureStart,hasExtendedEvents;{date_stop_legend},configureStop;';

        if (!$filterModel->intrinsic) {
            $palette .= '{form_legend},isLimited;';
        }

        return $palette;
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        $options = [
            'required' => false,
        ];

        $filterModel = $context->getFilterModel();

        if (!$filterModel->isLimited) {
            return $options;
        }

        if ($filterModel->configureStart
            && $filterModel->startAt
            && ($startAt = \strtotime($filterModel->startAt))
            && ($startAt = DateTimeHelper::timestampToDateTime($startAt)))
        {
            $options['from_min'] = $startAt;
            $options['to_min'] = $startAt;
        }

        if ($filterModel->configureStop
            && $filterModel->stopAt
            && ($stopAt = \strtotime($filterModel->stopAt))
            && ($stopAt = DateTimeHelper::timestampToDateTime($stopAt)))
        {
            $options['from_max'] = $stopAt;
            $options['to_max'] = $stopAt;
        }

        return $options;
    }
}