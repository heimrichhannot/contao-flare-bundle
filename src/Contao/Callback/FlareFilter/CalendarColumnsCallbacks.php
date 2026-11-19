<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\Filter\Element\CalendarCurrentFilterElement;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\Env;
use HeimrichHannot\FlareBundle\Util\SqlColumnKind;

/**
 * The column pickers of the calendar filter ({@see CalendarCurrentFilterElement}): their options, and their one-time
 * prefill when a row becomes a calendar filter, written together with the type change.
 *
 * @internal For internal use only. Do not call this class or its methods directly.
 */
final class CalendarColumnsCallbacks
{
    /** The event column each picker is prefilled with, by picker. */
    private const EVENT_COLUMNS = [
        'elm_periodStartField' => 'startTime',
        'elm_periodStopField' => 'endTime',
        'elm_recurringField' => 'recurring',
        'elm_recurrencesField' => 'recurrences',
        'elm_repeatEndField' => 'repeatEnd',
    ];

    private readonly bool $contao5;

    /**
     * @param bool|null $contao5 Whether Contao 5 runs; null asks {@see Env::isContao5()}.
     */
    public function __construct(
        private readonly Connection              $connection,
        private readonly FilterListTableResolver $tables,
        ?bool                                    $contao5 = null,
    ) {
        $this->contao5 = $contao5 ?? Env::isContao5();
    }

    /**
     * The columns of the edited filter's list table a picker offers: every physical column but the SQL date columns
     * the filter rejects. The record comes from `$dc->id`, so *edit all* gets each record's own table.
     *
     * @return array<string, string> Labels (`table.field`) by physical column name, sorted by label; empty without
     *   a table.
     */
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.elm_periodStartField.options')]
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.elm_periodStopField.options')]
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.elm_recurringField.options')]
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.elm_recurrencesField.options')]
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.elm_repeatEndField.options')]
    public function getColumnOptions(DataContainer $dc): array
    {
        $table = $this->tables->resolve((int) $dc->id);
        $options = [];

        foreach (DcaHelper::getColumns($table) as $column)
        {
            if ($column->kind === SqlColumnKind::Date) {
                continue;
            }

            $options[$column->name] = "{$table}.{$column->field}";
        }

        \asort($options);

        return $options;
    }

    /**
     * Contao 5: adds the prefill to the values a backend save is about to write when they make the row a calendar
     * filter. A value the request submitted wins over its prefill.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     *
     * @throws DbalException
     */
    #[AsCallback(FilterContainer::TABLE_NAME, 'config.onbeforesubmit')]
    public function onBeforeSubmit(array $values, DataContainer $dc): array
    {
        $id = (int) $dc->id;

        if (!$this->needsPrefill($values['type'] ?? null, $id)) {
            return $values;
        }

        return $values + $this->prefill($id);
    }

    /**
     * Contao 4.13, which writes each field right after its save callbacks and has no `onbeforesubmit`: writes the
     * prefill when the type becomes the calendar type — the type itself is written right after. Registered with
     * a low priority (-1000), so it runs after the DCA file's and other default-priority save callbacks.
     *
     * @throws DbalException
     */
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.type.save', priority: -1000)]
    public function onSaveType(mixed $value, DataContainer $dc): mixed
    {
        $id = (int) $dc->id;

        if ($this->contao5 || !$this->needsPrefill($value, $id)) {
            return $value;
        }

        $this->connection->update(FilterContainer::TABLE_NAME, $this->prefill($id), ['id' => $id]);

        return $value;
    }

    /**
     * Whether the row becomes a calendar filter: the new type is the calendar type and the stored one is not.
     *
     * @throws DbalException
     */
    private function needsPrefill(mixed $type, int $id): bool
    {
        if ($type !== CalendarCurrentFilterElement::TYPE) {
            return false;
        }

        $stored = $this->connection->fetchOne('SELECT type FROM tl_flare_filter WHERE id = ?', [$id]);

        return $stored !== CalendarCurrentFilterElement::TYPE;
    }

    /**
     * Each picker gets its event column when the list's table has it as a column the filter accepts (not a date
     * column), else stays empty; *Consider recurrence* is on when all three recurrence pickers are set; *Overlap*
     * is off.
     *
     * @return array<string, string|int> The seven columns to write, by column name.
     *
     * @throws DbalException
     */
    private function prefill(int $id): array
    {
        $table = $this->tables->resolve($id);
        $prefill = [];

        foreach (self::EVENT_COLUMNS as $picker => $name)
        {
            $column = DcaHelper::getColumn($table, $name);

            $prefill[$picker] = $column !== null && $column->kind !== SqlColumnKind::Date ? $column->name : '';
        }

        $recurrence = $prefill['elm_recurringField'] !== ''
            && $prefill['elm_recurrencesField'] !== ''
            && $prefill['elm_repeatEndField'] !== '';

        $prefill['elm_useRecurrence'] = $recurrence ? 1 : 0;
        $prefill['elm_overlap'] = 0;

        return $prefill;
    }
}
