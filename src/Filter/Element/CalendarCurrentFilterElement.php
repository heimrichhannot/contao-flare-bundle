<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBounds;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
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

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $value = $this->expectValue($value, DateRangeValue::class);

        $start = \strtotime((string) $scope->get('start_at')) ?: 0;
        $stop = \strtotime((string) $scope->get('stop_at')) ?: DateTimeHelper::maxTimestamp();

        if (!$scope->get('is_limited') && $scope->engineContext instanceof ValidationContext)
        {
            // Unlimited defaults only shape the initial display; the whole list is bounded by a fixed
            // value alone (controlled filters have none here, SPEC_FILTER_FORMS.md §4.2).
            if ($value === null) {
                return;
            }

            $start = 0;
            $stop = DateTimeHelper::maxTimestamp();
        }

        if (($from = $value?->from?->getTimestamp()) !== null)
        {
            if (!$scope->get('is_limited') || $from >= $start) {
                $start = $from;
            }
        }

        if (($to = $value?->to?->getTimestamp()) !== null)
        {
            if (!$scope->get('is_limited') || $to <= $stop) {
                $stop = $to;
            }
        }

        $builder->addPredicate(CalendarCurrentPredicate::class, [
            'start' => $start,
            'stop' => $stop,
            'has_extended_events' => $scope->get('has_extended_events'),
        ]);
    }

    public function getDateBounds(ElementScope $scope): DateBounds
    {
        return $this->resolveFormLimits($scope);
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
    private function resolveFormLimits(ElementScope $scope): DateBounds
    {
        if (!$scope->get('is_limited')) {
            return new DateBounds();
        }

        $min = null;
        $max = null;

        if ($scope->get('configure_start')
            && $scope->get('start_at')
            && ($startAt = \strtotime($scope->get('start_at'))))
        {
            $min = DateTimeHelper::timestampToDateTime($startAt);
        }

        if ($scope->get('configure_stop')
            && $scope->get('stop_at')
            && ($stopAt = \strtotime($scope->get('stop_at'))))
        {
            $max = DateTimeHelper::timestampToDateTime($stopAt);
        }

        return new DateBounds($min, $max);
    }
}
