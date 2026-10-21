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
 * The migration only updates rows; it never adds, drops or deletes. It reads the retired columns, so it requires
 * `intrinsic` and fails closed once that column is gone, and it waits for the renamed `ctl_` columns and for the
 * schema diff that adds `control` and `value`: `contao:migrate` re-checks migrations after the schema update.
 *
 * Element types and control names are frozen literals, not resolved through the registries: the migration describes
 * historical rows, and a later rename in live code must not change what already-migrated rows meant.
 */
class BackfillFilterValuesMigration extends AbstractMigration
{
    private const TABLE = 'tl_flare_filter';

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
        if (!$this->hasRequiredColumns()) {
            return false;
        }

        foreach ($this->fetchRows() as $row)
        {
            if ($this->computeChanges($row) !== []) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $updated = 0;

        foreach ($this->fetchRows() as $row)
        {
            if (!$changes = $this->computeChanges($row)) {
                continue;
            }

            $this->connection->update(self::TABLE, $changes, ['id' => (int) $row['id']]);
            ++$updated;
        }

        return $this->createResult(true, \sprintf('%s (%d filters updated)', $this->getName(), $updated));
    }

    private function hasRequiredColumns(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return false;
        }

        // Column names are case-insensitive in MySQL, so they are compared lowercased.
        $existing = \array_flip(\array_map(
            static fn (Column $column): string => \strtolower($column->getName()),
            $schemaManager->introspectTable(self::TABLE)->getColumns(),
        ));

        foreach (self::REQUIRED_COLUMNS as $column)
        {
            if (!isset($existing[\strtolower($column)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(): array
    {
        // `SELECT *`: the optional sources may be absent, and a missing key reads as empty.
        return $this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE . ' ORDER BY id');
    }

    /**
     * The column updates a row still needs. The result is non-empty exactly when the row matches the pending
     * predicate: its type has a control AND (it is controlled without a control yet, OR its value source converts to
     * a non-empty value while `value` is empty, OR it still uses the retired `null_false` binary choices). Existing
     * targets are never overwritten, so a run settles every row it touches.
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
