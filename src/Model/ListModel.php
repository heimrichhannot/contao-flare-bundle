<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * Class ListModel
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property bool $published
 * @property string $dc
 * @property string $fieldAutoItem
 * @property string $jumpToReader
 */
class ListModel extends Model
{
    protected static $strTable = ListContainer::TABLE_NAME;

    public function getAutoItemField(): string
    {
        return $this->fieldAutoItem ?: DcaHelper::tryGetColumnName($this->dc, 'alias', 'id');
    }
}