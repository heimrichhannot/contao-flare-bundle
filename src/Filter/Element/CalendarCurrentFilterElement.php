<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBounds;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\Contao\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\CalendarCurrentPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Bounds the list by the configured period (`start_at`/`stop_at`, null for an open side); a value narrows within
 * that bound, and an empty intersection of both matches nothing.
 */
#[AsFilterElement(type: self::TYPE, value: DateRangeValue::class)]
class CalendarCurrentFilterElement extends AbstractFilterElement implements DateBoundsContract
{
    public const TYPE = 'flare_calendar_current';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('start_at')->default(null)->allowedTypes(\DateTimeImmutable::class, 'null');
        $resolver->define('stop_at')->default(null)->allowedTypes(\DateTimeImmutable::class, 'null');
        $resolver->define('has_extended_events')->default(false)->allowedTypes('bool');
    }

    /**
     * A side applies only where its mode (`elm_configureStart`/`elm_configureStop`) is set. Its text is read alike
     * in every mode: a timestamp from the `date` mode, an expression from the `str` mode or the one a time span's
     * save callback wrote.
     *
     * @throws FilterException If a configured side holds unreadable text.
     */
    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('start_at', $this->readSide($model->elm_configureStart, $model->elm_startAt))
            ->set('stop_at', $this->readSide($model->elm_configureStop, $model->elm_stopAt))
            ->set('has_extended_events', (bool) $model->elm_hasExtendedEvents);
    }

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        $value = $this->expectValue($value, DateRangeValue::class);

        $start = $context->get('start_at')?->getTimestamp() ?? 0;
        $stop = $context->get('stop_at')?->getTimestamp() ?? DateTimeHelper::maxTimestamp();

        if (($from = $value?->from?->getTimestamp()) !== null && $from > $start) {
            $start = $from;
        }

        if (($to = $value?->to?->getTimestamp()) !== null && $to < $stop) {
            $stop = $to;
        }

        if ($start > $stop) {
            $builder->abort();
        }

        $builder->addPredicate(CalendarCurrentPredicate::class, [
            'start' => $start,
            'stop' => $stop,
            'has_extended_events' => $context->get('has_extended_events'),
        ]);
    }

    public function getDateBounds(ElementContext $context): DateBounds
    {
        return new DateBounds($context->get('start_at'), $context->get('stop_at'));
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette(
            '{date_start_legend},elm_configureStart,elm_hasExtendedEvents;'
            . '{date_stop_legend},elm_configureStop;'
        );

        $modes = [
            'elm_startAt' => $context->filterModel?->elm_configureStart,
            'elm_stopAt' => $context->filterModel?->elm_configureStop,
        ];

        foreach ($modes as $field => $mode)
        {
            $fieldBuilder = $builder->field($field);

            if ($mode === 'date') {
                $fieldBuilder->makeDatim();
            }

            $fieldBuilder->save($this->rejectUnreadableText(...));
        }
    }

    /**
     * @throws \RuntimeException If the text is unreadable; Contao shows the message at the field.
     */
    private function rejectUnreadableText(mixed $value): mixed
    {
        $text = \trim((string) $value);

        if ($text !== '' && DateTimeHelper::toDateTimeImmutable($text) === null) {
            throw new \RuntimeException($this->invalidMessage($text));
        }

        return $value;
    }

    /**
     * @throws FilterException If the side is configured and its text is unreadable.
     */
    private function readSide(mixed $mode, mixed $text): ?\DateTimeImmutable
    {
        if ((string) $mode === '') {
            return null;
        }

        $text = \trim((string) $text);

        if ($text === '') {
            return null;
        }

        return DateTimeHelper::toDateTimeImmutable($text) ?? throw new FilterException($this->invalidMessage($text));
    }

    private function invalidMessage(string $text): string
    {
        return $this->trans('filter.value.date_range.invalid', ['%expression%' => $text]);
    }
}
