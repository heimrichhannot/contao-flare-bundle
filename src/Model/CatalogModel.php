<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\CatalogContainer;

class CatalogModel extends Model
{
    protected static $strTable = CatalogContainer::TABLE_NAME;
}