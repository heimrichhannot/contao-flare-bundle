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
 * @property string $tstamp
 * @property string $type
 * @property string $title
 * @property string $field_start
 * @property string $field_stop
 *
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
}