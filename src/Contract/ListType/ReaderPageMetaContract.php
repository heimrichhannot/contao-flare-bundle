<?php

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;

interface ReaderPageMetaContract
{
    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMeta;
}