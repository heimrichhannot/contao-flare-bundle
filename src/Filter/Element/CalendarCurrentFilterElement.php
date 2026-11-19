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
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\SqlColumn;
use HeimrichHannot\FlareBundle\Util\SqlColumnKind;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Bounds the list by the configured period (`start_at`/`stop_at`, null for an open side); a value narrows within
 * that bound, and an empty intersection of both matches nothing.
 *
 * The entries' dates are read from the columns the editor picks (physical names of the list's table, Unix
 * timestamps): the start (`start_field`), optionally the end (`stop_field`, then `overlap`) and, with
 * `use_recurrence`, the recurrence columns. A picked column the table does not have, an SQL date column, an empty
 * required picker or a configured period whose end lies before its start breaks the filter.
 */
#[AsFilterElement(type: self::TYPE, value: DateRangeValue::class)]
class CalendarCurrentFilterElement extends AbstractFilterElement implements DateBoundsContract
{
    public const TYPE = 'flare_calendar_current';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('start_at')->default(null)->allowedTypes(\DateTimeImmutable::class, 'null');
        $resolver->define('stop_at')->default(null)->allowedTypes(\DateTimeImmutable::class, 'null');

        foreach (['start_field', 'stop_field', 'recurring_field', 'recurrences_field', 'repeat_end_field'] as $key) {
            $resolver->define($key)->default(null)->allowedTypes('string', 'null');
        }

        $resolver->define('overlap')->default(false)->allowedTypes('bool');
        $resolver->define('use_recurrence')->default(false)->allowedTypes('bool');
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
            ->set('start_field', $model->elm_periodStartField ?: null)
            ->set('stop_field', $model->elm_periodStopField ?: null)
            ->set('overlap', (bool) $model->elm_overlap)
            ->set('use_recurrence', (bool) $model->elm_useRecurrence)
            ->set('recurring_field', $model->elm_recurringField ?: null)
            ->set('recurrences_field', $model->elm_recurrencesField ?: null)
            ->set('repeat_end_field', $model->elm_repeatEndField ?: null);
    }

    /**
     * @throws FilterException If a picked column is missing or unusable, or the configured period is inverted.
     */
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        $value = $this->expectValue($value, DateRangeValue::class);

        $columns = $this->checkColumns($context);

        $startAt = $context->get('start_at');
        $stopAt = $context->get('stop_at');

        $this->checkPeriod($startAt, $stopAt);

        $start = $startAt?->getTimestamp() ?? 0;
        $stop = $stopAt?->getTimestamp() ?? DateTimeHelper::maxTimestamp();

        if (($from = $value?->from?->getTimestamp()) !== null && $from > $start) {
            $start = $from;
        }

        if (($to = $value?->to?->getTimestamp()) !== null && $to < $stop) {
            $stop = $to;
        }

        if ($start > $stop) {
            $builder->abort();
        }

        $options = [
            'start' => $start,
            'stop' => $stop,
            ...\array_map(static fn (SqlColumn $column): string => $column->name, $columns),
            'cast_fields' => \array_values(\array_map(
                static fn (SqlColumn $column): string => $column->name,
                \array_filter($columns, static fn (SqlColumn $column): bool => $column->kind === SqlColumnKind::Other),
            )),
        ];

        if (isset($columns['stop_field'])) {
            $options['overlap'] = $context->get('overlap');
        }

        $builder->addPredicate(CalendarCurrentPredicate::class, $options, TableAliasRegistry::ALIAS_MAIN);
    }

    public function getDateBounds(ElementContext $context): DateBounds
    {
        return new DateBounds($context->get('start_at'), $context->get('stop_at'));
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $overlap = (string) $context->filterModel?->elm_periodStopField !== '' ? ',elm_overlap' : '';

        $builder->palette(
            '{date_start_legend},elm_configureStart;'
            . '{date_stop_legend},elm_configureStop;'
            . "{date_columns_legend},elm_periodStartField,elm_periodStopField{$overlap},elm_useRecurrence;"
        );

        $builder->selector('elm_useRecurrence', 'elm_recurringField,elm_recurrencesField,elm_repeatEndField');

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

    /**
     * Checks every column the config uses: the start, the end if one is picked, the recurrence columns if recurrence
     * is switched on.
     *
     * @return array<string, SqlColumn> The columns by their config key.
     *
     * @throws FilterException If a column is not picked or cannot be used.
     */
    private function checkColumns(ElementContext $context): array
    {
        $roles = ['start_field' => 'start'];

        if ((string) $context->get('stop_field') !== '') {
            $roles['stop_field'] = 'stop';
        }

        if ($context->get('use_recurrence'))
        {
            $roles['recurring_field'] = 'recurring';
            $roles['recurrences_field'] = 'recurrences';
            $roles['repeat_end_field'] = 'repeat_end';
        }

        $columns = [];

        foreach ($roles as $key => $role) {
            $columns[$key] = $this->checkColumn($context->list->dc, $context->get($key), $role);
        }

        return $columns;
    }

    /**
     * The picked column as a timestamp column of the list's table.
     *
     * @param string $role The column's role, for the message: `start`, `stop`, `recurring`, `recurrences` or
     *   `repeat_end`.
     *
     * @throws FilterException If no column is picked, the table has no such column or it is an SQL date column.
     */
    private function checkColumn(?string $table, ?string $name, string $role): SqlColumn
    {
        $roleLabel = $this->trans("errors.calendar.role.{$role}");

        if ($name === null || $name === '') {
            throw new FilterException(
                $this->trans('errors.calendar.no_column', ['%role%' => $roleLabel]),
                method: __METHOD__,
            );
        }

        $column = DcaHelper::getColumn($table, $name) ?? throw new FilterException(
            $this->trans('errors.calendar.unknown_column', [
                '%role%' => $roleLabel,
                '%column%' => $name,
                '%table%' => (string) $table,
            ]),
            method: __METHOD__,
        );

        if ($column->kind === SqlColumnKind::Date) {
            throw new FilterException(
                $this->trans('errors.calendar.date_column', ['%role%' => $roleLabel, '%column%' => $name]),
                method: __METHOD__,
            );
        }

        return $column;
    }

    /**
     * @throws FilterException If both sides are configured and the end lies before the start.
     */
    private function checkPeriod(?\DateTimeImmutable $startAt, ?\DateTimeImmutable $stopAt): void
    {
        if ($startAt === null || $stopAt === null || $startAt <= $stopAt) {
            return;
        }

        throw new FilterException($this->trans('errors.calendar.inverted_period', [
            '%start%' => $startAt->format('Y-m-d H:i'),
            '%stop%' => $stopAt->format('Y-m-d H:i'),
        ]), method: __METHOD__);
    }

    private function invalidMessage(string $text): string
    {
        return $this->trans('filter.value.date_range.invalid', ['%expression%' => $text]);
    }
}
