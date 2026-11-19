<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Fills the column pickers of the `flare_calendar_current` filters of `tl_flare_filter` with the `tl_calendar_events`
 * columns the element's predicate used to hard-code, so a stored filter selects the same events as before: `startTime`
 * and `endTime` as the period, recurrence on with `recurring`, `recurrences` and `repeatEnd`, and `elm_overlap` from the
 * former `elm_hasExtendedEvents`.
 *
 * Each row is handled once, as in {@see RetireCalendarLimitMigration}: the migration manages its own marker column
 * `calendarColumnsFilled`, deliberately not part of the DCA. Existing rows start pending ('0'), the column default then
 * becomes '1', so rows created later are never pending, and a row's changes and its marker are written in one
 * statement. Rows of other types are marked done in the same run. A pending row whose start picker is already set (saved
 * between a schema-only update and this migration) is only marked, never overwritten.
 *
 * The migration waits for its columns: `elm_hasExtendedEvents` (after `RenameFilterColumnsMigration`'s rename) and the
 * seven columns the schema update adds. It does not wait for the backfill or `RetireCalendarLimitMigration`, whose
 * columns it neither reads nor writes.
 *
 * Guard: the migration requires `elm_hasExtendedEvents`, which it reads and which has left the DCA. The deletes
 * (`contao:migrate --with-deletes`) run only after all migrations and schema updates have run, a documented upgrade
 * requirement; they drop the source together with the marker, and the migration then stays quiet instead of finding
 * every row pending and switching recurrence back on or refilling an end column an editor cleared.
 */
class CalendarColumnsMigration extends AbstractMigration
{
    private const TABLE = 'tl_flare_filter';

    private const MARKER = 'calendarColumnsFilled';

    /**
     * The element types, frozen: the migration describes historical rows. Both keys of the filter are
     * recognized, because the rename migration may run before this one.
     */
    private const TYPES = ['flare_calendar_current', 'flare_time_window'];

    /** The picker columns and the event columns the predicate used to hard-code, frozen like the type. */
    private const PICKERS = [
        'elm_periodStartField' => 'startTime',
        'elm_periodStopField' => 'endTime',
        'elm_recurringField' => 'recurring',
        'elm_recurrencesField' => 'recurrences',
        'elm_repeatEndField' => 'repeatEnd',
    ];

    /** The columns this migration requires: the source, the pickers and the two switches. */
    private const REQUIRED_COLUMNS = [
        'type',
        'elm_hasExtendedEvents',
        'elm_periodStartField',
        'elm_periodStopField',
        'elm_recurringField',
        'elm_recurrencesField',
        'elm_repeatEndField',
        'elm_overlap',
        'elm_useRecurrence',
    ];

    public function __construct(private readonly Connection $connection) {}

    public function getName(): string
    {
        return 'FLARE: Fill the column pickers of the ' . self::TABLE . ' calendar filters';
    }

    public function shouldRun(): bool
    {
        if (null === $columns = $this->findColumns()) {
            return false;
        }

        foreach (self::REQUIRED_COLUMNS as $column)
        {
            if (!isset($columns[\strtolower($column)])) {
                return false;
            }
        }

        if (!isset($columns[\strtolower(self::MARKER)])) {
            return true;
        }

        return $this->fetchPendingRows() !== [];
    }

    public function run(): MigrationResult
    {
        // Plain DDL valid on MySQL 5.7/8 and MariaDB: the rows existing now start pending, rows created from now on
        // are never pending. The default switch is idempotent and runs on every run.
        if (!isset(($this->findColumns() ?? [])[\strtolower(self::MARKER)]))
        {
            $this->connection->executeStatement(\sprintf(
                "ALTER TABLE %s ADD %s CHAR(1) NOT NULL DEFAULT '0'",
                self::TABLE,
                self::MARKER,
            ));
        }

        $this->connection->executeStatement(\sprintf(
            "ALTER TABLE %s ALTER %s SET DEFAULT '1'",
            self::TABLE,
            self::MARKER,
        ));

        $filled = 0;

        foreach ($this->fetchPendingRows() as $row)
        {
            // Rows of other types have nothing to fill; the update below marks them done.
            if (!\in_array((string) ($row['type'] ?? ''), self::TYPES, true)) {
                continue;
            }

            // The marker is set in the same statement, so an interrupted run never processes a row twice.
            $changes = self::computeChanges($row);
            $this->connection->update(self::TABLE, $changes + [self::MARKER => '1'], ['id' => (int) $row['id']]);

            if ($changes !== []) {
                ++$filled;
            }
        }

        // Marking the rest keeps a later change of their type from turning them into calendar rows to fill.
        $this->connection->update(self::TABLE, [self::MARKER => '1'], [self::MARKER => '0']);

        $name = $this->getName();

        return $this->createResult(true, "{$name} ({$filled} calendar filters set to the event columns)");
    }

    /**
     * The table's column names, lowercased (MySQL column names are case-insensitive), or null without the table.
     *
     * @return array<string, int>|null
     */
    private function findColumns(): ?array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return null;
        }

        return \array_flip(\array_map(
            static fn (Column $column): string => \strtolower($column->getName()),
            $schemaManager->introspectTable(self::TABLE)->getColumns(),
        ));
    }

    /**
     * The rows not yet handled, of every type: a row of another type left pending by an interrupted run must still be
     * marked done.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchPendingRows(): array
    {
        return $this->connection->fetchAllAssociative(\sprintf(
            "SELECT * FROM %s WHERE %s = '0' ORDER BY id",
            self::TABLE,
            self::MARKER,
        ));
    }

    /**
     * The event columns, recurrence on and overlap from the former extended-events setting; nothing for a row whose
     * start picker is already set.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, int|string>
     */
    private static function computeChanges(array $row): array
    {
        if ((string) ($row['elm_periodStartField'] ?? '') !== '') {
            return [];
        }

        return self::PICKERS + [
            'elm_useRecurrence' => 1,
            'elm_overlap' => (int) (bool) (int) ($row['elm_hasExtendedEvents'] ?? 0),
        ];
    }
}
