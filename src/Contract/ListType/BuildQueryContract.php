<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

interface BuildQueryContract
{
    public function buildTableRegistry(TableAliasRegistry $registry): void;

    public function buildBaseQuery(SqlQueryStruct $struct): void;
}