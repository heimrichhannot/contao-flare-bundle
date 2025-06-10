<?php

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;

interface ReaderPageMetaContract
{
    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto;
}