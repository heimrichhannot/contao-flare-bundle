<?php

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageSchemaOrgConfig;

interface ReaderPageSchemaOrContract
{
    public function getReaderPageSchemaOrg(ReaderPageSchemaOrgConfig $config): ?array;
}