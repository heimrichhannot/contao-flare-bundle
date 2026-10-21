<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;

/**
 * Renames the `tl_flare_filter` columns to their owner-prefixed names: element columns carry `elm_`, control
 * columns `ctl_`, and `formAlias` becomes the framework column `alias` (SPEC_FILTER_FORMS §4.1, §11 A).
 *
 * Contao runs migrations before applying the schema diff, so renaming here preserves every filter's configuration.
 * Without this migration the diff would add the new columns empty and drop the old ones.
 *
 * `formVariant` → `control` is deliberately not migrated: the column only existed during v0.2 development and is
 * empty everywhere, so the schema diff may drop it and add `control`.
 */
class RenameFilterColumnsMigration extends AbstractMigration
{
    /**
     * The table name and the column map describe historical schemas and are therefore frozen literals, independent
     * of the live DCA.
     */
    private const TABLE = 'tl_flare_filter';

    /**
     * Old column name => new column name.
     */
    public const COLUMNS = [
        'fieldGeneric' => 'elm_fieldGeneric',
        'columnsGeneric' => 'elm_columnsGeneric',
        'usePublished' => 'elm_usePublished',
        'fieldPublished' => 'elm_fieldPublished',
        'invertPublished' => 'elm_invertPublished',
        'useStart' => 'elm_useStart',
        'fieldStart' => 'elm_fieldStart',
        'useStop' => 'elm_useStop',
        'fieldStop' => 'elm_fieldStop',
        'whichPtable' => 'elm_whichPtable',
        'fieldPid' => 'elm_fieldPid',
        'fieldPtable' => 'elm_fieldPtable',
        'tablePtable' => 'elm_tablePtable',
        'whitelistParents' => 'elm_whitelistParents',
        'groupWhitelistParents' => 'elm_groupWhitelistParents',
        'useWhitelistForOptionsOnly' => 'elm_useWhitelistForOptionsOnly',
        'equationLeft' => 'elm_equationLeft',
        'equationOperator' => 'elm_equationOperator',
        'equationRight' => 'elm_equationRight',
        'configureStart' => 'elm_configureStart',
        'startAt' => 'elm_startAt',
        'configureStop' => 'elm_configureStop',
        'stopAt' => 'elm_stopAt',
        'hasExtendedEvents' => 'elm_hasExtendedEvents',
        'isLimited' => 'elm_isLimited',
        'label' => 'ctl_label',
        'placeholder' => 'ctl_placeholder',
        'isMandatory' => 'ctl_isMandatory',
        'isMultiple' => 'ctl_isMultiple',
        'isExpanded' => 'ctl_isExpanded',
        'hasEmptyOption' => 'ctl_hasEmptyOption',
        'formatLabel' => 'ctl_formatLabel',
        'formatLabelCustom' => 'ctl_formatLabelCustom',
        'formatEmptyOption' => 'ctl_formatEmptyOption',
        'formatEmptyOptionCustom' => 'ctl_formatEmptyOptionCustom',
        'boolBinaryChoices' => 'ctl_boolBinaryChoices',
        'formAlias' => 'alias',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function getName(): string
    {
        return 'FLARE: Rename ' . self::TABLE . ' columns to their elm_/ctl_ prefixed and framework names';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return false;
        }

        return $this->findPending($schemaManager->introspectTable(self::TABLE)) !== [];
    }

    public function run(): MigrationResult
    {
        $table = $this->connection->createSchemaManager()->introspectTable(self::TABLE);
        $platform = $this->connection->getDatabasePlatform();
        $pending = $this->findPending($table);

        foreach ($pending as $old => $new)
        {
            // `CHANGE` rather than `RENAME COLUMN`: the latter requires MySQL 8.0 / MariaDB 10.5.2+, while Contao 4.13
            // still supports MySQL 5.7. `CHANGE` needs the full column definition, so the platform renders it from
            // the introspected column (type, length, nullability, default, charset and collation). One statement per
            // column, no ALGORITHM clause. All identifiers are constants, so no user input reaches the statement.
            $this->connection->executeStatement(\sprintf(
                'ALTER TABLE %s CHANGE %s %s',
                self::TABLE,
                $this->connection->quoteIdentifier($old),
                $platform->getColumnDeclarationSQL(
                    $this->connection->quoteIdentifier($new),
                    $table->getColumn($old)->toArray(),
                ),
            ));
        }

        return $this->createResult(true, \sprintf('%s (%d columns renamed)', $this->getName(), \count($pending)));
    }

    /**
     * The renames still to do: the old column exists and the new one does not. Column names are case-insensitive
     * in MySQL, so they are compared lowercased.
     *
     * @return array<string, string>
     */
    private function findPending(Table $table): array
    {
        $existing = \array_flip(\array_map(
            static fn (Column $column): string => \strtolower($column->getName()),
            $table->getColumns(),
        ));

        return \array_filter(
            self::COLUMNS,
            static fn (string $new, string $old): bool => isset($existing[\strtolower($old)])
                && !isset($existing[\strtolower($new)]),
            \ARRAY_FILTER_USE_BOTH,
        );
    }
}
