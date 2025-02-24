<?php

namespace HeimrichHannot\FlareBundle\Contao;

use HeimrichHannot\FlareBundle\DataContainer\CatalogContainer;
use HeimrichHannot\FlareBundle\DataContainer\CatalogFilterContainer;

class BackendModule
{
    public const CATEGORY = 'content';
    public const NAME = 'flare_catalog';
    public const TABLES = [
        CatalogContainer::TABLE_NAME,
        CatalogFilterContainer::TABLE_NAME
    ];
}