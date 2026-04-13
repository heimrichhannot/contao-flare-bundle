<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

interface ConfigureQueryContract
{
    public function configureTableRegistry(TableAliasRegistry $registry): void;

    public function configureBaseQuery(SqlQueryStruct $struct): void;
}