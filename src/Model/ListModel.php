<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Trait\AutoItemFieldGetterTrait;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\PtableInferrableInterface;

/**
 * Class ListModel
 */
class ListModel extends Model implements PtableInferrableInterface
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use PtableInferrableTrait;

    protected static $strTable = ListContainer::TABLE_NAME;
}