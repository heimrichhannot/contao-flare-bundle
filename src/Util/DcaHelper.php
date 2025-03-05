<?php

namespace HeimrichHannot\FlareBundle\Util;

use Contao\Controller;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * Class DcaHelper provides helper methods for data container arrays.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
class DcaHelper
{
    private static array $dcTableCache = [];

    protected static function getListDCTableFromDataContainer(?DataContainer $dc): ?string
    {
        if (!$dc
            || !($row = $dc?->activeRecord?->row())
            || !($pid = $row['pid'] ?? null))
        {
            return null;
        }

        if (isset(static::$dcTableCache[$pid])) {
            return static::$dcTableCache[$pid];
        }

        if (!($list = ListModel::findByPk($pid))
            || !($table = $list->dc))
        {
            return null;
        }

        return static::$dcTableCache[$pid] = $table;
    }

    public static function tryGetColumnName(DataContainer $dc, string $column, ?string $default = null): ?string
    {
        if (!$table = static::getListDCTableFromDataContainer($dc)) {
            return $default;
        }

        Controller::loadDataContainer($table);

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'][$column])) {
            return $default;
        }

        return $column;
    }

    public static function getFieldOptions(?DataContainer $dc, ?callable $predicate = null): array
    {
        if (!$table = static::getListDCTableFromDataContainer($dc)) {
            return [];
        }

        Controller::loadDataContainer($table);

        $options = [];
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] ?? [] as $field => $definition)
        {
            if ($predicate !== null && !$predicate($table, $field, $definition)) {
                continue;
            }

            $key = $table . '.' . $field;
            $options[$field] = $key;
        }

        \ksort($options);

        return $options;
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

        return \is_string($sql)
            && ($regex = static::getSqlTypeRegex($expectedType))
            && \preg_match($regex, $sql);
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

        $normalizeType = static fn(string $type) => match($type) {
            'int', 'integer' => 'integer',
            'bool', 'boolean' => 'boolean',
            'text', 'varchar', 'char', 'json', 'jsonb', 'string' => 'string',
            'binary', 'blob' => 'binary',
            default => $type,
        };

        return $normalizeType($givenType) === $normalizeType($expectedType);
    }
}