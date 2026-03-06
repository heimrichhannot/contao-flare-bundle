<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

readonly class ListExecutionContext
{
    public function __construct(
        public TableAliasRegistry $tableAliasRegistry,
        public SqlQueryStruct     $queryStruct,
    ) {}
}