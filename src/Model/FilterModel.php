<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use Contao\Model\Collection;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;

/**
 * Class FilterModel
 *
 * @property int $id
 * @property int $pid
 * @property int $sorting
 * @property int $tstamp
 * @property string $title
 * @property string $type
 * @property bool $published
 * @property bool $intrinsic
 * @property bool $isMandatory
 * @property bool $isMultiple
 * @property bool $isExpanded
 * @property bool $hasEmptyOption
 * @property bool $usePublished
 * @property bool $useStart
 * @property bool $useStop
 * @property bool $invertPublished
 * @property string $formatLabel
 * @property string $formatLabelCustom
 * @property string $formatEmptyOption
 * @property string $formatEmptyOptionCustom
 * @property string $whichPtable
 * @property string $fieldPublished
 * @property string $fieldStart
 * @property string $fieldStop
 * @property string $fieldPid
 * @property string $fieldPtable
 * @property string $tablePtable
 * @property string $whitelistParents
 * @property string $groupWhitelistParents
 * @property string $preselect
 * @property string $equationOperator
 * @property string $equationLeft
 * @property string $equationRight
 */
class FilterModel extends Model
{
    protected static $strTable = FilterContainer::TABLE_NAME;

    public static function findByPid(int $pid, ?bool $published = null): Collection
    {
        $result = $published !== null
            ? static::findBy(['pid=?', 'published=?'], [$pid, $published], ['order' => 'sorting'])
            : static::findBy(['pid=?'], [$pid], ['order' => 'sorting']);

        if (!$result) {
            return new Collection([], static::getTable());
        }

        if (!$result instanceof Collection) {
            return new Collection([$result], static::getTable());
        }

        return $result;
    }

    public function whichPtable_disableAutoOption(): void
    {
        $GLOBALS['TL_DCA'][FilterModel::getTable()]['fields']['whichPtable']['options'] = ['dynamic', 'static'];
        $GLOBALS['TL_DCA'][FilterModel::getTable()]['fields']['whichPtable']['default'] = ['dynamic'];

        if ($this->whichPtable === 'auto')
        {
            $this->whichPtable = 'dynamic';
            $this->save();
        }
    }
}