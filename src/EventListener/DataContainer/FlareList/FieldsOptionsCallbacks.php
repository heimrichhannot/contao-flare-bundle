<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareList;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Util\DcaFieldFilter;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsOptionsCallbacks
{
    public const TABLE_NAME = ListContainer::TABLE_NAME;

    public function __construct(
        private ContaoFramework         $contaoFramework,
        private ListContainer           $listContainer,
        private TranslationManager      $translationManager,
        private ResourceFinderInterface $resourceFinder,
        private ListTypeRegistry        $listTypeRegistry,
    ) {}

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->listTypeRegistry->all() as $alias => $listTypeConfig)
        {
            $options[$alias] = $this->translationManager->listModel($alias);
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
        if (empty($row = DcaHelper::rowOf($dc)) || empty($table = $row['dc'])) {
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
     * @internal For internal use only. Do not call this method directly.
     *
     * @see contao/dca/tl_flare_list.php -> `$dca['fields']['sortSettings']['fields']['column']['options_callback']`
     */
    public function getFieldOptions_columns(DataContainer $dc): array
    {
        $row = DcaHelper::rowOf($dc);
        return DcaHelper::getFieldOptions($row['dc'] ?? null);
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

        if (!$tables = $db?->listTables()) {
            return [];
        }

        $tables = \array_filter($tables, static fn(string $table) => $db->tableExists($table));
        return \array_combine($tables, $tables) ?: [];
    }
}