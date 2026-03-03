<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrableInterface;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use HeimrichHannot\FlareBundle\Trait\AutoItemFieldGetterTrait;

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

    public function getListProperty(string $name): mixed
    {
        return $this->{$name};
    }
}