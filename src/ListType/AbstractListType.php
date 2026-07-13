<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListType;

use HeimrichHannot\FlareBundle\Contract;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

abstract class AbstractListType implements Contract\ListType\ConfigureQueryContract
{
    public function configureTableRegistry(TableAliasRegistry $registry): void {}

    public function configureBaseQuery(SqlQueryStruct $struct): void {}
}
