<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Fills the framework columns `control` and `value` and the control columns of `tl_flare_filter` from the retired
 * columns `intrinsic`, `boolMode`, `preselect` and `prefill` (SPEC_FILTER_FORMS §11 B).
 *
 * The migration never drops or deletes. It reads the retired columns, so it requires `intrinsic` and fails closed once
 * that column is gone, and it waits for the renamed `ctl_` columns and for the schema diff that adds `control` and
 * `value`: `contao:migrate` re-checks migrations after the schema update.
 *
 * Each row is backfilled once. The retired columns stay until deletes are run manually, and their content cannot tell
 * a row still to migrate from one an editor changed (or created) afterwards: a filter made intrinsic by clearing its
 * control still reads `intrinsic = 0`. The migration therefore manages its own marker column `legacyBackfilled`, which
 * is deliberately not part of the DCA: existing rows start at '0' and are set to '1' once processed, and the column
 * default becomes '1', so rows created later are never pending. The schema diff proposes dropping the marker together
 * with the other retired columns.
 *
 * Element types and control names are frozen literals, not resolved through the registries: the migration describes
 * historical rows, and a later rename in live code must not change what already-migrated rows meant.
 */
class BackfillFilterValuesMigration extends AbstractMigration
{
    private const TABLE = 'tl_flare_filter';

    private const MARKER = 'legacyBackfilled';

    /**
     * The columns this migration requires: the source `intrinsic`, the renamed control columns it reads or fills, and
     * the targets. The other sources (`boolMode`, `preselect`, `prefill`) read as empty when absent.
     */
    private const REQUIRED_COLUMNS = [
        'intrinsic',
        'control',
        'value',
        'ctl_boolBinaryChoices',
        'ctl_isMandatory',
        'ctl_isMultiple',
        'ctl_isExpanded',
        'ctl_hasEmptyOption',
        'ctl_formatEmptyOption',
        'ctl_formatEmptyOptionCustom',
        'ctl_placeholder',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function getName(): string
    {
        return 'FLARE: Backfill ' . self::TABLE . ' control and value from the retired filter columns';
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

        return !isset($columns[\strtolower(self::MARKER)]) || $this->fetchPendingRows() !== [];
    }

    public function run(): MigrationResult
    {
        // Plain DDL valid on MySQL 5.7/8 and MariaDB; existing rows start as pending ('0').
        if (!isset(($this->findColumns() ?? [])[\strtolower(self::MARKER)])) {
            $this->connection->executeStatement(\sprintf(
                "ALTER TABLE %s ADD %s CHAR(1) NOT NULL DEFAULT '0'",
                self::TABLE,
                self::MARKER,
            ));
        }

        $updated = 0;

        foreach ($this->fetchPendingRows() as $row)
        {
            // The marker is set in the same statement, so an interrupted run never processes a row twice.
            $changes = $this->computeChanges($row);
            $this->connection->update(self::TABLE, $changes + [self::MARKER => '1'], ['id' => (int) $row['id']]);

            if ($changes !== []) {
                ++$updated;
            }
        }

        // Rows of element types without a control have nothing to backfill; marking them keeps a later change of
        // their type from turning them pending.
        $this->connection->update(self::TABLE, [self::MARKER => '1'], [self::MARKER => '0']);

        // Rows created from now on are never pending.
        $this->connection->executeStatement(\sprintf(
            "ALTER TABLE %s ALTER %s SET DEFAULT '1'",
            self::TABLE,
            self::MARKER,
        ));

        return $this->createResult(true, \sprintf('%s (%d filters updated)', $this->getName(), $updated));
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
     * The rows not yet backfilled whose element type has a control; other types have nothing to backfill.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchPendingRows(): array
    {
        // `SELECT *`: the optional sources may be absent, and a missing key reads as empty.
        $rows = $this->connection->fetchAllAssociative(\sprintf(
            "SELECT * FROM %s WHERE %s = '0' ORDER BY id",
            self::TABLE,
            self::MARKER,
        ));

        return \array_values(\array_filter(
            $rows,
            static fn (array $row): bool => self::controlFor((string) ($row['type'] ?? ''), '') !== null,
        ));
    }

    /**
     * The column updates a pending row needs. Existing targets are never overwritten.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function computeChanges(array $row): array
    {
        $type = (string) ($row['type'] ?? '');

        if (($control = self::controlFor($type, (string) ($row['boolMode'] ?? ''))) === null) {
            return [];
        }

        $intrinsic = (bool) (int) ($row['intrinsic'] ?? 0);
        $changes = [];
        $assignsControl = !$intrinsic && (string) ($row['control'] ?? '') === '';

        if ($assignsControl) {
            $changes['control'] = $control;
        }

        if (($value = self::valueFor($type, $intrinsic, $row)) !== '' && (string) ($row['value'] ?? '') === '') {
            $changes['value'] = $value;
        }

        if (($row['ctl_boolBinaryChoices'] ?? '') === 'null_false') {
            $changes['ctl_boolBinaryChoices'] = 'true_false';
        }

        if ($assignsControl) {
            $changes += self::emptyOptionChanges($type, $row);
        }

        return $changes;
    }

    /**
     * The control of a controlled filter, or null for element types without a value (they never get a control).
     */
    private static function controlFor(string $type, string $boolMode): ?string
    {
        return match ($type) {
            'flare_archive', 'flare_fieldValueChoice', 'flare_dcaSelectField', 'cfg_tags_choice' => 'flare_choice',
            'flare_dateRange', 'flare_calendar_current' => 'flare_date_range',
            'flare_search_keywords' => 'flare_keywords',
            'flare_bool' => $boolMode === 'ternary' ? 'flare_choice_bool' : 'flare_checkbox',
            default => null,
        };
    }

    /**
     * The value in the value provider's storage format, copied only where the old code read it, so no leftover
     * becomes a restriction. Date types never stored a value.
     *
     * @param array<string, mixed> $row
     */
    private static function valueFor(string $type, bool $intrinsic, array $row): string
    {
        return match ($type) {
            'flare_bool' => self::convertBool($row['preselect'] ?? null),
            'flare_fieldValueChoice', 'flare_dcaSelectField', 'cfg_tags_choice' => self::convertChoices(
                $row['preselect'] ?? null,
            ),
            'flare_archive' => $intrinsic ? '' : self::convertChoices($row['preselect'] ?? null),
            'flare_search_keywords' => $intrinsic ? self::convertKeywords($row['prefill'] ?? null) : '',
            default => '',
        };
    }

    /**
     * `true` / `false` (or `1` / `0`) become `'1'` / `'0'`; `null`, empty and unreadable values become empty.
     */
    private static function convertBool(mixed $stored): string
    {
        $stored = \strtolower(\trim((string) $stored));

        if ($stored === '' || $stored === 'null') {
            return '';
        }

        return match (\filter_var($stored, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)) {
            true => '1',
            false => '0',
            null => '',
        };
    }

    /**
     * A single key or a serialized array of keys becomes a serialized list of keys; the keys are already canonical.
     * A falsy column was never read by the old code.
     */
    private static function convertChoices(mixed $stored): string
    {
        if (!$stored = (string) $stored) {
            return '';
        }

        $keys = @\unserialize($stored, ['allowed_classes' => false]);

        if (!\is_array($keys)) {
            $keys = [$stored];
        }

        $list = [];

        foreach ($keys as $key)
        {
            if ((\is_string($key) || \is_int($key)) && ($key = (string) $key) !== '') {
                $list[] = $key;
            }
        }

        return $list === [] ? '' : \serialize(\array_values(\array_unique($list)));
    }

    /**
     * A falsy column was never read by the old code.
     */
    private static function convertKeywords(mixed $stored): string
    {
        return (string) $stored ?: '';
    }

    /**
     * Keeps the look of existing choice widgets: single-select field value choices always showed an empty option;
     * single-select, non-expanded DCA select and Codefog tags choices showed Symfony's placeholder as an empty option,
     * whose text becomes the empty option's custom label (SPEC_FILTER_FORMS §7.3). Archive maps 1:1.
     * Multiple or expanded widgets keep `ctl_placeholder` as the HTML placeholder.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private static function emptyOptionChanges(string $type, array $row): array
    {
        if ((bool) (int) ($row['ctl_isMultiple'] ?? 0)) {
            return [];
        }

        if ($type === 'flare_fieldValueChoice') {
            return ['ctl_hasEmptyOption' => '1'];
        }

        if (($type !== 'flare_dcaSelectField' && $type !== 'cfg_tags_choice')
            || (bool) (int) ($row['ctl_isExpanded'] ?? 0))
        {
            return [];
        }

        $placeholder = (string) ($row['ctl_placeholder'] ?? '');

        if ($placeholder === '')
        {
            // Without a placeholder text the old widget fell back to these Symfony placeholders.
            return [
                'ctl_hasEmptyOption' => '1',
                'ctl_formatEmptyOption' => (bool) (int) ($row['ctl_isMandatory'] ?? 0)
                    ? 'empty_option.prompt'
                    : 'empty_option.no_selection',
            ];
        }

        return [
            'ctl_hasEmptyOption' => '1',
            'ctl_formatEmptyOption' => 'custom',
            'ctl_formatEmptyOptionCustom' => $placeholder,
            'ctl_placeholder' => '',
        ];
    }
}
