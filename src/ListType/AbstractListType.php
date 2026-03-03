<?php

namespace HeimrichHannot\FlareBundle\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageSchemaOrgConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageSchemaOrgContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;

abstract class AbstractListType implements PaletteContract, ReaderPageMetaContract, ReaderPageSchemaOrgContract
{
    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto
    {
        return null;
    }

    public function getReaderPageSchemaOrg(ReaderPageSchemaOrgConfig $config): ?array
    {
        return null;
    }
}