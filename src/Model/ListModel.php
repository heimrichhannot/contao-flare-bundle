<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrableInterface;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * Class ListModel
 */
class ListModel extends Model implements PtableInferrableInterface
{
    use DocumentsListModelTrait;
    use PtableInferrableTrait;

    protected static $strTable = ListContainer::TABLE_NAME;

    public function getListDriverType(): ?string
    {
        return $this->type;
    }

    public function getAutoItemField(): string
    {
        return $this->fieldAutoItem ?: DcaHelper::tryGetColumnName($this->dc, 'alias', 'id');
    }
}
