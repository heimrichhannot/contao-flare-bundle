<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;

/**
 * Class ListModel
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property bool $published
 * @property string $dc
 * @property string $fieldAutoItem
 */
class ListModel extends Model
{
    protected static $strTable = ListContainer::TABLE_NAME;
}