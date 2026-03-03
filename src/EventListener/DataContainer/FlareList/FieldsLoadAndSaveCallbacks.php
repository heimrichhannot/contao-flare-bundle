<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareList;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsLoadAndSaveCallbacks
{
    public const TABLE_NAME = ListContainer::TABLE_NAME;

    public function __construct(
        private ListContainer $listContainer,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.fieldAutoItem.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldAutoItem.save')]
    public function fieldAutoItem(mixed $value, DataContainer $dc): string
    {
        if (!$table = $this->listContainer->getListedTableName($dc)) {
            return '';
        }

        return $value ?: DcaHelper::tryGetColumnName($table, 'alias', 'id');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.save')]
    public function fieldPid(mixed $value, DataContainer $dc): string
    {
        if (!$table = $this->listContainer->getListedTableName($dc)) {
            return $value;
        }

        return $value ?: DcaHelper::tryGetColumnName($table, 'pid', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.save')]
    public function fieldPtable(mixed $value, DataContainer $dc): string
    {
        if (!$table = $this->listContainer->getListedTableName($dc)) {
            return $value;
        }

        return $value ?: DcaHelper::tryGetColumnName($table, 'ptable', '');
    }
}