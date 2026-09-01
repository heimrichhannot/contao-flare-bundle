<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;

/**
 * Renames the `tl_content.flare_list` column to `tl_content.flare_listId`.
 *
 * The old name collided with the Twig variable `flare_list`, which holds the list view. Whenever Contao spreads the
 * content row into the top-level template context (the legacy template shape), the integer column value shadowed the
 * view, so `{% set flare_list = flare_list ?? flare.createView %}` never created the view.
 *
 * Contao runs migrations before applying the schema diff, so renaming here preserves every list assignment. Without
 * this migration the diff would add an empty `flare_listId` and drop `flare_list`, unassigning every list.
 */
class RenameContentListColumnMigration extends AbstractMigration
{
    private const OLD_COLUMN = 'flare_list';

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function getName(): string
    {
        return \sprintf(
            'FLARE: Rename %1$s.%2$s to %1$s.%3$s',
            ContentContainer::TABLE_NAME,
            self::OLD_COLUMN,
            ContentContainer::FIELD_LIST,
        );
    }

    public function shouldRun(): bool
    {
        if (!$this->connection->fetchOne(\sprintf("SHOW TABLES LIKE '%s'", ContentContainer::TABLE_NAME))) {
            return false;
        }

        $columns = \array_map(
            'strtolower',
            $this->connection->fetchFirstColumn(\sprintf('SHOW COLUMNS FROM %s', ContentContainer::TABLE_NAME)),
        );

        // Column names are case-insensitive in MySQL, so compare lowercased. Note that `flare_listId` folds to
        // `flare_listid` and therefore cannot be mistaken for `flare_list`.
        return \in_array(\strtolower(self::OLD_COLUMN), $columns, true)
            && !\in_array(\strtolower(ContentContainer::FIELD_LIST), $columns, true);
    }

    public function run(): MigrationResult
    {
        // `CHANGE` rather than `RENAME COLUMN`: the latter requires MySQL 8.0 / MariaDB 10.5.2+, while Contao 4.13
        // still supports MySQL 5.7. All identifiers are constants, so no user input reaches the statement.
        $this->connection->executeStatement(\sprintf(
            'ALTER TABLE %s CHANGE %s %s int(10) unsigned NOT NULL DEFAULT 0',
            ContentContainer::TABLE_NAME,
            self::OLD_COLUMN,
            ContentContainer::FIELD_LIST,
        ));

        return $this->createResult(true, $this->getName() . ' (list assignments preserved)');
    }
}
