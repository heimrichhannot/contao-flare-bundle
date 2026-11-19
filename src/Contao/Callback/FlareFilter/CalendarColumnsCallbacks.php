<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\Filter\Element\CalendarCurrentFilterElement;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\SqlColumnKind;

/**
 * The column pickers of the calendar filter ({@see CalendarCurrentFilterElement}).
 *
 * @internal For internal use only. Do not call this class or its methods directly.
 */
final class CalendarColumnsCallbacks
{
    public function __construct(
        private readonly FilterListTableResolver $tables,
    ) {}

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
}
