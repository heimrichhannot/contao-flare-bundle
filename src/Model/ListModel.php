<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;

class ListModel extends Model
{
    protected static $strTable = ListContainer::TABLE_NAME;
}