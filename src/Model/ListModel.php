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
 * @property bool $comments_enabled
 * @property string $fieldAutoItem
 * @property string $jumpToReader
 * @property array  $sortSettings
 * @property string $metaTitleFormat
 * @property string $metaDescriptionFormat
 * @property string $metaRobotsFormat
 */
class ListModel extends Model
{
    protected static $strTable = ListContainer::TABLE_NAME;

    public function getAutoItemField(): string
    {
        return $this->fieldAutoItem ?: DcaHelper::tryGetColumnName($this->dc, 'alias', 'id');
    }
}