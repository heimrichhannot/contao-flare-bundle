<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
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

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        $value = $this->expectValue($value, DateRangeValue::class);
        $config = $builder->scope->filter->config;

        if (!$config['is_limited'] && $builder->scope->engineContext instanceof ValidationContext) {
            return;
        }

        $start = \strtotime((string) $config['start_at']) ?: 0;
        $stop = \strtotime((string) $config['stop_at']) ?: DateTimeHelper::maxTimestamp();

        if (($from = $value?->from?->getTimestamp()) !== null)
        {
            if (!$config['is_limited'] || $from >= $start) {
                $start = $from;
            }
        }

        if (($to = $value?->to?->getTimestamp()) !== null)
        {
            if (!$config['is_limited'] || $to <= $stop) {
                $stop = $to;
            }
        }

        $builder->addPredicate(CalendarCurrentPredicate::class, [
            'start' => $start,
            'stop' => $stop,
            'has_extended_events' => $config['has_extended_events'],
        ]);
    }

    public function getDateBounds(FilterScope $scope): array
    {
        return $this->resolveFormLimits($scope->filter->config);
    }

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
    {
        $dca->palette(
            '{date_start_legend},elm_configureStart,elm_hasExtendedEvents;'
            . '{date_stop_legend},elm_configureStop;'
            . '{form_legend},elm_isLimited;'
        );
    }

    /**
     * Resolves the form's lower and upper date limits from the canonical config, replicating
     * the former handleFormTypeOptions() logic (from_min/to_min and from_max/to_max).
     *
     * @param array<string, mixed> $config
     *
     * @return array{0: ?\DateTime, 1: ?\DateTime}
     */
    private function resolveFormLimits(array $config): array
    {
        if (!$config['is_limited']) {
            return [null, null];
        }

        $min = null;
        $max = null;

        if ($config['configure_start']
            && $config['start_at']
            && ($startAt = \strtotime($config['start_at'])))
        {
            $min = DateTimeHelper::timestampToDateTime($startAt);
        }

        if ($config['configure_stop']
            && $config['stop_at']
            && ($stopAt = \strtotime($config['stop_at'])))
        {
            $max = DateTimeHelper::timestampToDateTime($stopAt);
        }

        return [$min, $max];
    }
}
