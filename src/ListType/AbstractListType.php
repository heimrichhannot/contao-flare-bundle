<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;

abstract class AbstractListType implements
    Contract\PaletteContract,
    Contract\ListType\ReaderPageMetaContract,
    Contract\ListType\ConfigureQueryContract
{
    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    public function configureTableRegistry(TableAliasRegistry $registry): void {}

    public function configureBaseQuery(SqlQueryStruct $struct): void {}

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMeta
    {
        return null;
    }
}