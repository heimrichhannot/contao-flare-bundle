<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Renames the type of the calendar filters of `tl_flare_filter` from `flare_calendar_current` to `flare_time_window`,
 * the key the element is registered under since it reads the date fields of any table.
 *
 * Both keys are frozen literals, not the element's `TYPE` constant: the migration describes historical rows, and a
 * later rename in live code must not change what it rewrites.
 *
 * The comparison is byte-exact (`CAST(type AS BINARY) = ?`, valid on MySQL 5.7/8 and MariaDB): `type` has a
 * case-insensitive collation, and a separately registered custom type such as `Flare_Calendar_Current` must not be
 * converted. Only `type` changes, in one statement.
 *
 * The migration needs only the `type` column and keeps no marker: it runs while a row of the old key exists, so it is
 * idempotent, and a version or undo record restored from before the upgrade, which writes the old key back, is renamed
 * again by the next `contao:migrate` (the settings of such a record are not recovered by it).
 *
 * Order: on a v0.1 database the rename runs in the first pass, before `BackfillFilterValuesMigration`,
 * `RetireCalendarLimitMigration` and `CalendarColumnsMigration` find the columns the schema update adds. They recognize
 * both keys, so they process the renamed rows alike, whichever runs first.
 */
class RenameTimeWindowTypeMigration extends AbstractMigration
{
    private const TABLE = 'tl_flare_filter';

    /** The element's former key, frozen. */
    private const OLD = 'flare_calendar_current';

    /** The element's key, frozen like the former one. */
    private const NEW = 'flare_time_window';

    public function __construct(private readonly Connection $connection) {}

    public function getName(): string
    {
        return 'FLARE: Rename the ' . self::TABLE . ' calendar filters to ' . self::NEW;
    }

    public function shouldRun(): bool
    {
        if (!isset(($this->findColumns() ?? [])['type'])) {
            return false;
        }

        return $this->connection->fetchOne(
            \sprintf('SELECT 1 FROM %s WHERE CAST(type AS BINARY) = ? LIMIT 1', self::TABLE),
            [self::OLD],
        ) !== false;
    }

    public function run(): MigrationResult
    {
        $renamed = (int) $this->connection->executeStatement(
            \sprintf('UPDATE %s SET type = ? WHERE CAST(type AS BINARY) = ?', self::TABLE),
            [self::NEW, self::OLD],
        );

        $name = $this->getName();

        return $this->createResult(true, "{$name} ({$renamed} filters renamed)");
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
}
