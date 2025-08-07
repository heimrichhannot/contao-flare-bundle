<?php

namespace HeimrichHannot\FlareBundle\Util;

class DcaFieldFilter
{
    public static function ptable(string $table, string $field, array $definition): bool
    {
        if (\str_contains($field, 'ptable')) {
            return true;
        }

        if (($definition['inputType'] ?? null) === 'text'
            && !DcaHelper::testSQLType($definition['sql'] ?? null, 'int')) {
            return true;
        }

        return DcaHelper::testSQLType($definition['sql'] ?? null, 'text');
    }

    public static function pid(string $table, string $field, array $definition): bool
    {
        if (\str_contains($field, 'pid')
            || \is_array($definition['relation'] ?? null)
            || \is_string($definition['foreignKey'] ?? null)) {
            return true;
        }

        return DcaHelper::testSQLType($definition['sql'] ?? null, 'int');
    }
}