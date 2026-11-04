<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBounds;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\Contao\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\CalendarCurrentPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, value: DateRangeValue::class)]
class CalendarCurrentFilterElement extends AbstractFilterElement implements DateBoundsContract
{
    public const TYPE = 'flare_calendar_current';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('is_limited')->default(false)->allowedTypes('bool');
        $resolver->define('configure_start')->default(null)->allowedTypes('string', 'null');
        $resolver->define('configure_stop')->default(null)->allowedTypes('string', 'null');
        $resolver->define('start_at')->default(null)->allowedTypes('string', 'null');
        $resolver->define('stop_at')->default(null)->allowedTypes('string', 'null');
        $resolver->define('has_extended_events')->default(false)->allowedTypes('bool');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('is_limited', (bool) $model->elm_isLimited)
            ->set('configure_start', $model->elm_configureStart ?: null)
            ->set('configure_stop', $model->elm_configureStop ?: null)
            ->set('start_at', $model->elm_startAt ?: null)
            ->set('stop_at', $model->elm_stopAt ?: null)
            ->set('has_extended_events', (bool) $model->elm_hasExtendedEvents);
    }

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        $value = $this->expectValue($value, DateRangeValue::class);

        $start = \strtotime((string) $context->get('start_at')) ?: 0;
        $stop = \strtotime((string) $context->get('stop_at')) ?: DateTimeHelper::maxTimestamp();

        if (!$context->get('is_limited') && $context->intent instanceof ValidationIntent)
        {
            // Unlimited defaults only shape the initial display; the whole list is bounded by a fixed
            // value alone (controlled filters have none here).
            if ($value === null) {
                return;
            }

            $start = 0;
            $stop = DateTimeHelper::maxTimestamp();
        }

        if (($from = $value?->from?->getTimestamp()) !== null)
        {
            if (!$context->get('is_limited') || $from >= $start) {
                $start = $from;
            }
        }

        if (($to = $value?->to?->getTimestamp()) !== null)
        {
            if (!$context->get('is_limited') || $to <= $stop) {
                $stop = $to;
            }
        }

        $builder->addPredicate(CalendarCurrentPredicate::class, [
            'start' => $start,
            'stop' => $stop,
            'has_extended_events' => $context->get('has_extended_events'),
        ]);
    }

    public function getDateBounds(ElementContext $context): DateBounds
    {
        return $this->resolveFormLimits($context);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette(
            '{date_start_legend},elm_configureStart,elm_hasExtendedEvents;'
            . '{date_stop_legend},elm_configureStop;'
            . '{date_limits_legend},elm_isLimited;'
        );
    }

    /**
     * Resolves the form's lower and upper date limits from the canonical config, replicating
     * the former handleFormTypeOptions() logic (from_min/to_min and from_max/to_max).
     */
    private function resolveFormLimits(ElementContext $context): DateBounds
    {
        if (!$context->get('is_limited')) {
            return new DateBounds();
        }

        $min = null;
        $max = null;

        if ($context->get('configure_start')
            && $context->get('start_at')
            && ($startAt = \strtotime($context->get('start_at'))))
        {
            $min = DateTimeHelper::timestampToDateTime($startAt);
        }

        if ($context->get('configure_stop')
            && $context->get('stop_at')
            && ($stopAt = \strtotime($context->get('stop_at'))))
        {
            $max = DateTimeHelper::timestampToDateTime($stopAt);
        }

        return new DateBounds($min, $max);
    }
}
