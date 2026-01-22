<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsFilterElement(
    type: self::TYPE,
    formType: DateRangeFilterType::class,
)]
class CalendarCurrentElement extends AbstractFilterElement
{
    public const TYPE = 'flare_calendar_current';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedData = $context->getFormData();
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

        $colStartTime = $qb->column('startTime');
        $colRepeatEnd = $qb->column('repeatEnd');
        $colRecurrences = $qb->column('recurrences');
        $colRecurring = $qb->column('recurring');

        $or = [
            "{$colStartTime} >= :start AND {$colStartTime} <= :end",  // event starts in range
            $qb->expr()->and(  // event is recurring
                $qb->expr()->eq($colRecurring, 1),
                $qb->expr()->lte($colStartTime, ':end'),  // event starts before the end of the range
                $qb->expr()->or(
                    $qb->expr()->eq($colRecurrences, 0),  // 0 = infinite recurrences
                    $qb->expr()->gte($colRepeatEnd, ':start'),
                ),
            ),
        ];

        if ($filterModel->hasExtendedEvents)
        {
            $colEndTime = $qb->column('endTime');

            $or[] = "{$colEndTime} >= :start AND {$colEndTime} <= :end";  // event ends in the range
            $or[] = "{$colStartTime} <= :start AND {$colEndTime} >= :end";  // event is within the range
        }

        $qb->whereOr(...$or);

        $qb->setParameter('start', $start);
        $qb->setParameter('end', $stop);
    }

    #[AsEventListener('flare.filter_element.' . self::TYPE . '.invoking')]
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

        if (!$filterModel?->intrinsic) {
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