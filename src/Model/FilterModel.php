<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use Contao\Model\Collection;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;

/**
 * Class FilterModel
 */
class FilterModel extends Model
{
    use FilterModelDocTrait;

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
        $GLOBALS['TL_DCA'][self::getTable()]['fields']['whichPtable']['options'] = ['dynamic', 'static'];
        $GLOBALS['TL_DCA'][self::getTable()]['fields']['whichPtable']['default'] = ['dynamic'];

        if ($this->whichPtable === 'auto')
        {
            $this->whichPtable = 'dynamic';
            $this->save();
        }
    }
}