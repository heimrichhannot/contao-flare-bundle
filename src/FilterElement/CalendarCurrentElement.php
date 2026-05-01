<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Type\CalendarCurrentFilterType;
use HeimrichHannot\FlareBundle\Form\Type\DateRangeFilterType;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;

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
    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void
    {
        $filter = $invocation->filter;

        if (!$filter->isLimited && $invocation->context instanceof ValidationContext) {
            return;
        }

        $value = $this->processRuntimeValue($invocation->getValue(), $invocation->list, $filter) ?? [];
        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        $start = \strtotime($filter->startAt) ?: 0;
        $stop = \strtotime($filter->stopAt) ?: DateTimeHelper::maxTimestamp();

        if ($from instanceof \DateTimeInterface)
        {
            $from = $from->getTimestamp();

            if (!$filter->isLimited || $from >= $start) {
                $start = $from;
            }
        }

        if ($to instanceof \DateTimeInterface)
        {
            $to = $to->getTimestamp();

            if (!$filter->isLimited || $to <= $stop) {
                $stop = $to;
            }
        }

        $builder->add(CalendarCurrentFilterType::class, [
            'start' => $start,
            'stop' => $stop,
            'has_extended_events' => (bool) $filter->hasExtendedEvents,
        ]);
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, ConfiguredFilter $filter): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        if (!\array_key_exists('from', $value) && !\array_key_exists('to', $value))
        {
            if (\count($value) !== 2)
            {
                return null;
            }

            $value = \array_values($value);

            return [
                'from' => $this->mixedToDateTime($value[0] ?? null),
                'to' => $this->mixedToDateTime($value[1] ?? null),
            ];
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

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

    public function getPalette(PaletteConfig $config): ?string
    {
        $filterModel = $config->getFilterModel();

        $palette = '{date_start_legend},configureStart,hasExtendedEvents;{date_stop_legend},configureStop;';

        if (!$filterModel?->intrinsic) {
            $palette .= '{form_legend},isLimited;';
        }

        return $palette;
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
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
