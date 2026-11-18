<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Retires the `is_limited` setting of the calendar filters of `tl_flare_filter` without widening a list or narrowing a
 * reader: it makes the filters that had it off exclusive to the list view, switches on the period sides whose leftover
 * text v0.1 applied, and counts the filters whose meaning in the list view narrows.
 *
 * The baseline is v0.1, as v0.2 has not been released. v0.1's calendar element bounded the list view by its period,
 * and the reader only where `is_limited` was on: with it off, the element skipped itself for a `ValidationContext`, so
 * the reader and the breadcrumb ignored the filter (every intrinsic row had it off). The element now follows one rule,
 * v0.1's `is_limited` behavior made permanent: the configured period bounds every list the filter applies to, and a
 * value only narrows within it. Applied to every row as it is, that rule never widens a list, so no control or value
 * is moved or touched.
 *
 * A row with `is_limited` off becomes exclusive to the list view (`exclusive`, `outputs` = the list view), so the
 * reader, the breadcrumb and the lookup keep ignoring it, as v0.1's reader did. In the list view the one rule applies;
 * where the filter has a control or a stored value with a set side, the list can become narrower, and the result
 * message counts those filters, so editors can check them. A row with `is_limited` on stays non-exclusive.
 *
 * Every row of the type also gets each side switched on (`str`) whose `elm_configureStart`/`elm_configureStop` is
 * blank but whose `elm_startAt`/`elm_stopAt` holds a non-numeric text v0.1 could read, so the backend shows the bound
 * that applies today and the new rule keeps applying it. The text is read as v0.1 read it, with plain `strtotime()`:
 * a time-span key such as `this_week` it cannot read, so that side is open today and stays off. An unreadable or
 * numeric leftover stays off as well.
 *
 * Order: `RenameFilterColumnsMigration` renames the element columns and `BackfillFilterValuesMigration` assigns the
 * control and the value. The switch-on needs only the renamed columns and the exclusive step `exclusive` and
 * `outputs`, which the schema update adds; the count needs the control and the value. So the migration waits for all
 * these columns and for the backfill to have nothing pending; `contao:migrate` checks again after each pass. Contao
 * orders migrations of equal priority by class name, which this does not rely on.
 *
 * Each row is handled once. Run again, the migration would switch on a side an editor has switched off since (the
 * save callback does not clear the text), and `elm_isLimited` stays in the database, so it cannot serve as a marker.
 * As in the backfill, the migration manages its own marker column `calendarLimitRetired`, deliberately not part of the
 * DCA: existing rows start pending ('0'), the column default then becomes '1', so rows created later are never
 * pending, and a row's changes and its marker are written in one statement. Rows of other types are marked done in the
 * same run. A DCA field defaulting to '0' and set on backend saves (the query design's D31) is not used: a row created
 * outside a backend save would start pending and be rewritten.
 *
 * The migration requires `elm_isLimited`, which only a database that had the v0.1 calendar element has. The schema
 * diff offers to drop the marker together with `elm_isLimited` among the manual deletes, and `contao:migrate
 * --with-deletes` runs the migrations again after the schema diff; without the column the migration stays quiet
 * instead of starting over.
 *
 * Saves between the schema update and the migration are not guarded: editors do not save filters in that window.
 */
class RetireCalendarLimitMigration extends AbstractMigration
{
    private const TABLE = 'tl_flare_filter';

    private const MARKER = 'calendarLimitRetired';

    /** The element type, frozen: the migration describes historical rows. */
    private const TYPE = 'flare_calendar_current';

    /** The list view output's key, frozen like the type. */
    private const LIST_VIEW = 'list_view';

    /**
     * The columns this migration requires: the renamed period columns, `elm_isLimited`, the backfill's targets and the
     * exclusive setting.
     */
    private const REQUIRED_COLUMNS = [
        'type',
        'control',
        'value',
        'exclusive',
        'outputs',
        'elm_isLimited',
        'elm_configureStart',
        'elm_configureStop',
        'elm_startAt',
        'elm_stopAt',
    ];

    /** The text column of each side, by its mode column. */
    private const SIDES = [
        'elm_configureStart' => 'elm_startAt',
        'elm_configureStop' => 'elm_stopAt',
    ];

    public function __construct(
        private readonly Connection                    $connection,
        private readonly BackfillFilterValuesMigration $backfill,
    ) {}

    public function getName(): string
    {
        return 'FLARE: Retire the period limit setting of the ' . self::TABLE . ' calendar filters';
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

        if ($this->backfill->shouldRun()) {
            return false;
        }

        if (!isset($columns[\strtolower(self::MARKER)])) {
            return true;
        }

        return $this->fetchPendingRows() !== [];
    }

    public function run(): MigrationResult
    {
        // Plain DDL valid on MySQL 5.7/8 and MariaDB, as in the backfill: the rows existing now start pending, rows
        // created from now on are never pending. The default switch is idempotent and runs on every run.
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

        $updated = 0;
        $toCheck = 0;

        foreach ($this->fetchPendingRows() as $row)
        {
            // Rows of other types have nothing to retire; the update below marks them done.
            if ((string) ($row['type'] ?? '') !== self::TYPE) {
                continue;
            }

            // The marker is set in the same statement, so an interrupted run never processes a row twice.
            $changes = self::computeChanges($row);
            $this->connection->update(self::TABLE, $changes + [self::MARKER => '1'], ['id' => (int) $row['id']]);

            if ($changes !== []) {
                ++$updated;
            }

            if (self::needsCheck($row)) {
                ++$toCheck;
            }
        }

        // Rows of other types have nothing to retire; marking them keeps a later change of their type from turning
        // them pending.
        $this->connection->update(self::TABLE, [self::MARKER => '1'], [self::MARKER => '0']);

        $name = $this->getName();

        return $this->createResult(
            true,
            "{$name} ({$updated} filters updated, {$toCheck} calendar filters with a control or a fixed value now stay"
                . ' within their period: check them)',
        );
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
     * marked done, or a later change of its type would turn it into a calendar row to retire.
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
     * Switches on each side that is off but holds a leftover text v0.1 applied, and makes a row with `is_limited` off
     * exclusive to the list view. No control or value is written.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private static function computeChanges(array $row): array
    {
        $changes = [];

        foreach (self::SIDES as $configure => $text)
        {
            if ((string) ($row[$configure] ?? '') === '' && self::isReadableLeftover((string) ($row[$text] ?? ''))) {
                $changes[$configure] = 'str';
            }
        }

        if (!self::isLimited($row))
        {
            // v0.1's reader ignored the filter.
            $changes['exclusive'] = '1';
            $changes['outputs'] = \serialize([self::LIST_VIEW]);
        }

        return $changes;
    }

    /**
     * Whether v0.1 applied the text: non-empty, non-numeric and readable by plain `strtotime()`, which knows no
     * time-span keys.
     */
    private static function isReadableLeftover(string $text): bool
    {
        $text = \trim($text);

        return $text !== '' && !\is_numeric($text) && \strtotime($text) !== false;
    }

    /**
     * Whether the filter's meaning in the list view narrows under the one rule: `is_limited` was off, and the filter
     * has a control or a stored value with a set side.
     *
     * @param array<string, mixed> $row
     */
    private static function needsCheck(array $row): bool
    {
        if (self::isLimited($row)) {
            return false;
        }

        if ((string) ($row['control'] ?? '') !== '') {
            return true;
        }

        $range = StringUtil::deserialize($row['value'] ?? null, true);

        foreach (\array_pad(\array_slice(\array_values($range), 0, 2), 2, null) as $side)
        {
            if (\is_int($side) || (\is_string($side) && \trim($side) !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether `is_limited` was on: the period bounded v0.1's reader too.
     *
     * @param array<string, mixed> $row
     */
    private static function isLimited(array $row): bool
    {
        return (bool) (int) ($row['elm_isLimited'] ?? 0);
    }
}
