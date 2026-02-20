<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use HeimrichHannot\FlareBundle\Trait\AutoItemFieldGetterTrait;
use HeimrichHannot\FlareBundle\Util\PtableInferrableInterface;

/**
 * Class ListModel
 */
class ListModel extends Model implements PtableInferrableInterface, ListDataSourceInterface
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use PtableInferrableTrait;

    protected static $strTable = ListContainer::TABLE_NAME;

    public function getListType(): string
    {
        return $this->type;
    }

    public function getListTable(): string
    {
        return $this->dc;
    }

    public function getListData(): array
    {
        return $this->arrData;
    }
}