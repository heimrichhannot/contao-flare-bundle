<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Callback\FlareList;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Util\DcaFieldFilter;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsOptionsCallbacks
{
    public const TABLE_NAME = ListContainer::TABLE_NAME;

    public function __construct(
        private ContaoFramework         $contaoFramework,
        private ListContainer           $listContainer,
        private ListDriverRegistry      $listDriverRegistry,
        private ResourceFinderInterface $resourceFinder,
        private TranslatorInterface     $translator,
    ) {}

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->listDriverRegistry->keys() as $type)
        {
            $options[$type] = $this->translator->trans($type, [], 'flare_list_driver');
        }

        return $options;
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.dc.options')]
    public function getDataContainerOptions(): array
    {
        $options = [];

        $files = $this->resourceFinder->findIn('dca')->name('tl_*.php');

        foreach ($files as $file) {
            $name = $file->getBasename('.php');
            $options[$name] = $name;
        }

        \ksort($options);

        return $options;
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldAutoItem.options')]
    public function getFieldAutoItemOptions(?DataContainer $dc = null): array
    {
        if (!($row = DcaHelper::rowOf($dc)) || !($table = $row['dc'])) {
            return ['alias' => 'alias', 'id' => 'id'];
        }

        Controller::loadDataContainer($table);

        $choices = [];

        $fields = \array_keys($GLOBALS['TL_DCA'][$table]['fields'] ?? []);

        foreach ($fields as $field) {
            $choices[$field] = $table . '.' . $field;
        }

        return $choices;
    }

    /**
     * The column select of a `sortSettings` group row (mvo/contao-group-widget copies the callback onto each row's
     * virtual field).
     *
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.sortSettings.fields.column.options')]
    public function getFieldOptions_sortSettings(DataContainer $dc): array
    {
        $row = DcaHelper::rowOf($dc);
        return DcaHelper::getFieldOptions($row['dc'] ?? null, alias: TableAliasRegistry::ALIAS_MAIN);
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.options')]
    public function getFieldOptions_fieldPid(DataContainer $dc): array
    {
        if (!$table = $this->listContainer->getListedTableName($dc)) {
            return [];
        }

        return DcaHelper::getFieldOptions($table, DcaFieldFilter::pid(...));
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.options')]
    public function getFieldOptions_fieldPtable(DataContainer $dc): array
    {
        if (!$table = $this->listContainer->getListedTableName($dc)) {
            return [];
        }

        return DcaHelper::getFieldOptions($table, DcaFieldFilter::ptable(...));
    }

    #[AsCallback(self::TABLE_NAME, 'fields.tablePtable.options')]
    public function getFieldOptions_tablePtable(DataContainer $dc): array
    {
        $db = $this->contaoFramework->createInstance(Database::class);

        if (!$db || !$tables = $db->listTables()) {
            return [];
        }

        $tables = \array_filter($tables, $db->tableExists(...));
        return \array_combine($tables, $tables) ?: [];
    }
}
