<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\PtableInferrableInterface;

/**
 * Class ListModel
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property bool $published
 * @property string $dc
 * @property bool $comments_enabled
 * @property bool $comments_sendNativeEmails
 * @property string $fieldAutoItem
 * @property string $jumpToReader
 * @property array  $sortSettings
 * @property string $metaTitleFormat
 * @property string $metaDescriptionFormat
 * @property string $metaRobotsFormat
 * @property bool   $hasParent
 * @property string $fieldPid
 * @property string $fieldPtable
 * @property string $tablePtable
 * @property string $whichPtable
 */
class ListModel extends Model implements PtableInferrableInterface
{
    use PtableInferrableTrait;

    protected static $strTable = ListContainer::TABLE_NAME;

    public function getAutoItemField(): string
    {
        return $this->fieldAutoItem ?: DcaHelper::tryGetColumnName($this->dc, 'alias', 'id');
    }
}