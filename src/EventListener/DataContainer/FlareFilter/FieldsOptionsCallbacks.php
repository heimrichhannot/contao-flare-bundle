<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use HeimrichHannot\FlareBundle\Util\DcaFieldFilter;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsOptionsCallbacks
{
    public const TABLE_NAME = FilterContainer::TABLE_NAME;

    public function __construct(
        private ContaoFramework       $contaoFramework,
        private FilterContainer       $filterContainer,
        private FilterElementRegistry $filterElementRegistry,
        private ListQueryManager      $listQueryManager,
        private TranslationManager    $translationManager,
        private TranslatorInterface   $translator,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getFieldOptions_type(): array
    {
        $options = [];

        foreach ($this->filterElementRegistry->all() as $alias => $filterElement)
        {
            $options[$alias] = $this->translationManager->filterElement($alias);
        }

        \asort($options, \SORT_NATURAL);

        return $options;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.options')]
    public function getFieldOptions_fieldBool(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'checkbox',
        );
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.options')]
    public function getFieldOptions_fieldDatim(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'text' && ($definition['eval']['rgxp'] ?? null) === 'datim',
        );
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.options')]
    public function getFieldOptions_fieldPid(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions($dc, DcaFieldFilter::pid(...));
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.options')]
    public function getFieldOptions_fieldPtable(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions($dc, DcaFieldFilter::ptable(...));
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldGeneric.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.columnsGeneric.options')]
    public function getFieldOptions_fieldGeneric(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions($dc);
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

    #[AsCallback(self::TABLE_NAME, 'fields.whitelistParents.options')]
    public function getOptions_whitelistParents(DataContainer $dc): array
    {
        [$filterModel, $listModel] = $this->filterContainer->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return [];
        }

        $inferrer = new PtableInferrer($filterModel, $listModel);

        if ($ptable = $inferrer->getDcaMainPtable())
        {
            return DcaHelper::getArchiveOptions($ptable);
        }
        // else => this is a group field

        if (\count($groupParts = \explode('__', $dc->field)) !== 3) {
            return [];
        }

        [$field, $groupField, $index] = $groupParts;
        // $sourcePtableField = \sprintf('%s__tablePtable__%s', $field, $index);

        if ($field !== 'groupWhitelistParents' || $groupField !== 'whitelistParents') {
            return [];
        }

        if (!$savedGroups = StringUtil::deserialize($filterModel->groupWhitelistParents ?? '')) {
            return [];
        }

        if (!$group = $savedGroups[(int) $index] ?? null) {
            return [];
        }

        $ptable = $group['tablePtable'] ?? null;

        return DcaHelper::getArchiveOptions($ptable);
    }

    #[AsCallback(self::TABLE_NAME, 'fields.formatLabel.options')]
    public function getOptions_formatLabel(DataContainer $dc): array
    {
        return $this->getFormatOptions('formatLabel');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.formatEmptyOption.options')]
    public function getOptions_formatEmptyOption(DataContainer $dc): array
    {
        return $this->getFormatOptions('formatEmptyOption');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.configureStart.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.configureStop.options')]
    public function getOptions_configureDate(): array
    {
        return [
            '',
            'custom' => ['date', 'str'],
            ...DateTimeHelper::getTimeSpanOptions(),
        ];
    }

    #[AsCallback(self::TABLE_NAME, 'fields.targetAlias.options')]
    public function getOptions_targetAlias(?DataContainer $dc): array
    {
        if (!$dc?->id || !$filterModel = DcaHelper::modelOf($dc)) {
            return [ListQueryManager::ALIAS_MAIN => ListQueryManager::ALIAS_MAIN];
        }

        try
        {
            $listModel = $filterModel->getRelated('pid');
        }
        catch (\Throwable)
        {
            return [];
        }

        if (!$listModel instanceof ListModel) {
            return [];
        }

        $tables = $this->listQueryManager->prepare($listModel)->getTables();
        $options = [];

        foreach ($tables as $alias => $table)
        {
            $options[$alias] = \sprintf('%s [%s]', $alias, $table);
        }

        return $options;
    }

    public function getFormatOptions(string $field, ?string $prefix = null): array
    {
        $catalogue = $this->translator->getCatalogue();
        $labels = $catalogue->all('flare_form');

        $options = [];

        $prefix ??= match ($field) {
            'formatLabel' => 'format.',
            'formatEmptyOption' => 'empty_option.',
            default => null
        };

        foreach ($labels as $key => $label)
        {
            if ($prefix && !\str_starts_with($key, $prefix)) {
                continue;
            }

            $options[$key] = $label . ' [' . $key . ']';
        }

        unset($options['custom']);

        \asort($options);

        /** @noinspection PhpTranslationDomainInspection */
        return ['custom' => $this->translator->trans("tl_flare_filter.{$field}_custom", [], 'contao_tl_flare_filter')] + $options;
    }
}