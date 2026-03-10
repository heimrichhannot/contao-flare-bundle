<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

trait QueryHelperTrait
{
    public function column(string $column, string $alias = TableAliasRegistry::ALIAS_MAIN): string
    {
        return sprintf('%s.%s', $alias, $column);
    }

    public function makeJoinOn(string $alias1, string $col1, string $alias2, string $col2): string
    {
        return sprintf('%s.%s = %s.%s', $alias1, $col1, $alias2, $col2);
    }
}