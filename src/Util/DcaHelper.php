<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

use Contao\Controller;
use Contao\DataContainer;
use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * Class DcaHelper provides helper methods for data container arrays.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
class DcaHelper
{
    public static function modelOf(?DataContainer $dc): ?Model
    {
        if (!$dc || !($id = $dc->id ?? null)) {
            return null;
        }

        $modelClass = Model::getClassFromTable($dc->table);
        if (!\class_exists($modelClass)) {
            return null;
        }

        if (!$model = $modelClass::findByPk($id)) {
            return null;
        }

        return $model;
    }

    public static function rowOf(?DataContainer $dc): ?array
    {
        if (!$dc) {
            return null;
        }

        if (\method_exists($dc, 'getCurrentRecord')) {
            return $dc->getCurrentRecord();
        }

        if (!$model = static::modelOf($dc)) {
            return null;
        }

        return $model->row();
    }

    protected static function getListDCTableFromDataContainer(?DataContainer $dc): ?string
    {
        if (!$dc
            || !($row = static::rowOf($dc))
            || !($pid = $row['pid'] ?? null))
        {
            return null;
        }

        static $dcTableCache;
        $dcTableCache ??= [];

        if (isset($dcTableCache[$pid])) {
            return$dcTableCache[$pid];
        }

        if (!($list = ListModel::findByPk($pid))
            || !($table = $list->dc))
        {
            return null;
        }

        return $dcTableCache[$pid] = $table;
    }

    public static function tryGetColumnName(
        DataContainer|string|null $dc_or_table,
        string                    $column,
        ?string                   $default = null,
    ): ?string {
        if (\is_string($dc_or_table))
        {
            $table = $dc_or_table;
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif (!$table = static::getListDCTableFromDataContainer($dc_or_table))
        {
            return $default;
        }

        Controller::loadDataContainer($table);

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'][$column])) {
            return $default;
        }

        return $column;
    }

    public static function getFieldOptions(
        DataContainer|string|null $dc_or_table,
        ?callable                 $predicate = null,
        ?string                   $alias = null,
    ): array {
        if (\is_string($dc_or_table))
        {
            $table = $dc_or_table;
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif (!$table = static::getListDCTableFromDataContainer($dc_or_table))
        {
            return [];
        }

        Controller::loadDataContainer($table);

        $options = [];
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] ?? [] as $field => $definition)
        {
            if ($predicate !== null && !$predicate($table, $field, $definition)) {
                continue;
            }

            $label = $table . '.' . $field;
            $key = $alias ? "{$alias}.{$field}" : $field;
            $options[$key] = $label;
        }

        \ksort($options);

        return $options;
    }

    /**
     * Classifies a DCA field's `sql` definition by the kind of column it declares.
     *
     * A string, or an array's `columnDefinition`, is read by its leading declaration word; otherwise the
     * array's Doctrine `type` decides.
     *
     * @return SqlColumnKind|null Null for an empty `sql`.
     */
    public static function classifySql(array|string|null $sql): ?SqlColumnKind
    {
        if (!$sql) {
            return null;
        }

        if (\is_string($sql)) {
            return static::classifySqlWord($sql);
        }

        $definition = $sql['columnDefinition'] ?? null;
        if (\is_string($definition) && $definition !== '') {
            return static::classifySqlWord($definition);
        }

        return match (\strtolower((string) ($sql['type'] ?? ''))) {
            'smallint', 'integer', 'bigint', 'boolean' => SqlColumnKind::Integer,
            'date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable',
            'time', 'time_immutable' => SqlColumnKind::Date,
            default => SqlColumnKind::Other,
        };
    }

    protected static function classifySqlWord(string $definition): SqlColumnKind
    {
        if (!\preg_match('/^\s*([a-z]+)/i', $definition, $matches)) {
            return SqlColumnKind::Other;
        }

        return match (\strtolower($matches[1])) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => SqlColumnKind::Integer,
            'date', 'datetime', 'timestamp', 'time', 'year' => SqlColumnKind::Date,
            default => SqlColumnKind::Other,
        };
    }

    /**
     * The table's DCA fields that have a physical column (an `sql` definition and a plain column name).
     *
     * @return list<SqlColumn>
     */
    public static function getColumns(?string $table): array
    {
        if ($table === null || $table === '') {
            return [];
        }

        Controller::loadDataContainer($table);

        $columns = [];
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] ?? [] as $field => $definition)
        {
            $sql = $definition['sql'] ?? null;
            if (!\is_array($sql) && !\is_string($sql)) {
                continue;
            }

            if (!$kind = static::classifySql($sql)) {
                continue;
            }

            $name = \is_array($sql) && \is_string($sql['name'] ?? null) ? $sql['name'] : (string) $field;
            if (!\preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                continue;
            }

            $columns[] = new SqlColumn($name, (string) $field, $kind);
        }

        return $columns;
    }

    /**
     * The column whose physical name equals `$name`, case-insensitively.
     */
    public static function getColumn(?string $table, string $name): ?SqlColumn
    {
        foreach (static::getColumns($table) as $column)
        {
            if (\strcasecmp($column->name, $name) === 0) {
                return $column;
            }
        }

        return null;
    }

    public static function testSQLType(array|string|null $sql, string $expectedType): bool
    {
        if (!$sql) {
            return false;
        }

        if (\is_array($sql))
        {
            if (!$sqlType = $sql['type'] ?? null) {
                return false;
            }

            return static::isStrSqlType((string) $sqlType, $expectedType);
        }

        if ($regex = static::getSqlTypeRegex($expectedType)) {
            return (bool) \preg_match($regex, $sql);
        }

        return false;
    }

    protected static function getSqlTypeRegex(string $type): ?string
    {
        return match (\strtolower($type)) {
            'int', 'integer' => '/\b(?:int|smallint|bigint|tinyint)\(\d+\)/i',
            'bool', 'boolean' => '/\b(?:char|int|tinyint)\(1\)/i',
            'text', 'varchar', 'char', 'json', 'string' => '/\b(?:text|mediumtext|varchar|char\((?!1\))\d+\)|json|jsonb)\b/i',
            'binary', 'blob' => '/\b(?:blob|binary\(\d+\))\b/i',
            default => null,
        };
    }

    protected static function isStrSqlType(string $givenType, string $expectedType): bool
    {
        $givenType = \strtolower($givenType);
        $expectedType = \strtolower($expectedType);

        if ($givenType === $expectedType) {
            return true;
        }

        $normalizeType = static fn (string $type): string => match($type) {
            'int', 'integer' => 'integer',
            'bool', 'boolean' => 'boolean',
            'text', 'varchar', 'char', 'json', 'jsonb', 'string' => 'string',
            'binary', 'blob' => 'binary',
            default => $type,
        };

        return $normalizeType($givenType) === $normalizeType($expectedType);
    }

    public static function getArchiveOptions(?string $table): array
    {
        if (!$table) {
            return [];
        }

        Controller::loadDataContainer($table);

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)
            || !\method_exists($modelClass, 'findAll')
            || !$models = $modelClass::findAll())
        {
            return [];
        }

        $labelFields = match ($table) {
            'tl_article' => ['title'],
            'tl_content' => ['headline'],
            'tl_files' => ['name'],
            'tl_user' => ['username', 'lastname', 'firstname', 'email'],
            default => [],
        };

        $fields = $GLOBALS['TL_DCA'][$table]['fields'] ?? [];

        if (!$labelFields && $label = \array_intersect_key($fields, \array_flip(['title', 'name', 'headline']))) {
            $labelFields = \array_keys($label);
        }

        $options = [];

        foreach ($models as $model)
        {
            $labelParts = [];
            foreach ($labelFields as $field)
            {
                if (!$value = $model->{$field}) {
                    continue;
                }

                if (\str_starts_with($value, "a:") && \str_contains($value, '{')
                    && ($arrValue = StringUtil::deserialize($value))
                    && (!$value = $arrValue['value'] ?? null))
                {
                    continue;
                }

                $labelParts[] = $value;
            }

            $label = \implode(' / ', $labelParts);

            if ($model->type)
            {
                Controller::loadLanguageFile($table);

                $type = $GLOBALS['TL_DCA'][$table]['fields']['type']['reference'][$model->type]
                    ?? $GLOBALS['TL_LANG'][$table][$model->type]
                    ?? $model->type;

                $type = \is_array($type) ? ($type[0] ?? $model->type) : $type;
                $label .= " ({$type})";
            }

            $label .= " [{$model->id}]";

            $options[$model->id] = \trim($label);
        }

        return $options;
    }
}
