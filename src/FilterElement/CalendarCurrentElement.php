<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
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
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $value = $inv->getValue();
        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        $start = \strtotime($inv->filter->startAt) ?: 0;
        $stop = \strtotime($inv->filter->stopAt) ?: DateTimeHelper::maxTimestamp();

        if ($from instanceof \DateTimeInterface)
        {
            $from = $from->getTimestamp();

            if (!$inv->filter->isLimited || $from >= $start) {
                $start = $from;
            }
        }

        if ($to instanceof \DateTimeInterface)
        {
            $to = $to->getTimestamp();

            if (!$inv->filter->isLimited || $to <= $stop) {
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

        if ($inv->filter->hasExtendedEvents)
        {
            $colEndTime = $qb->column('endTime');

            $or[] = "{$colEndTime} >= :start AND {$colEndTime} <= :end";  // event ends in the range
            $or[] = "{$colStartTime} <= :start AND {$colEndTime} >= :end";  // event is within the range
        }

        $qb->whereOr(...$or);

        $qb->setParameter('start', $start);
        $qb->setParameter('end', $stop);
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        if (\array_key_exists('from', $value) || \array_key_exists('to', $value))
        {
            $from = $value['from'] ?? null;
            $to = $value['to'] ?? null;
        }
        elseif (\count($value) === 2)
        {
            $value = \array_values($value);

            $from = $value[0] ?? null;
            $to = $value[1] ?? null;
        }
        else
        {
            return null;
        }

        return [
            'from' => $this->mixedToDateTime($from),
            'to' => $this->mixedToDateTime($to),
        ];
    }

    private function mixedToDateTime(mixed $input): ?\DateTimeInterface
    {
        if (!$input) {
            return null;
        }

        if ($input instanceof \DateTimeInterface) {
            return $input;
        }

        if (\is_numeric($input)) {
            return \DateTimeImmutable::createFromFormat('U', $input);
        }

        if (\is_string($input)) {
            return new \DateTimeImmutable($input);
        }

        return null;
    }

    #[AsEventListener('flare.filter_element.' . self::TYPE . '.invoking')]
    public function onInvoking(FilterElementInvokingEvent $event): void
    {
        $filter = $event->getInvocation()->filter;

        if (!$filter->isLimited && $event->getContext() instanceof ValidationContext) {
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

    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['required'] = false;

        $filter = $event->filter;

        if (!$filter->isLimited) {
            return;
        }

        if ($filter->configureStart
            && $filter->startAt
            && ($startAt = \strtotime($filter->startAt))
            && ($startAt = DateTimeHelper::timestampToDateTime($startAt)))
        {
            $event->options['from_min'] = $startAt;
            $event->options['to_min'] = $startAt;
        }

        if ($filter->configureStop
            && $filter->stopAt
            && ($stopAt = \strtotime($filter->stopAt))
            && ($stopAt = DateTimeHelper::timestampToDateTime($stopAt)))
        {
            $event->options['from_max'] = $stopAt;
            $event->options['to_max'] = $stopAt;
        }
    }
}