<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use HeimrichHannot\FlareBundle\Util\DcaFieldFilter;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsOptionsCallbacks
{
    public const TABLE_NAME = FilterContainer::TABLE_NAME;

    public function __construct(
        private ContaoFramework             $contaoFramework,
        private FilterContainer             $filterContainer,
        private FilterControlRegistry       $filterControlRegistry,
        private FilterElementRegistry       $filterElementRegistry,
        private TranslatorInterface         $translator,
        private ListSpecBuilderFactory      $listFactory,
        private ListExecutionContextFactory $listExecutionContextFactory,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getFieldOptions_type(): array
    {
        $options = [];

        foreach ($this->filterElementRegistry->keys() as $type)
        {
            $filterElement = $this->filterElementRegistry->getService($type);

            if ($filterElement instanceof IsSupportedContract && !$filterElement->isSupported())
            {
                continue;
            }

            $options[$type] = $this->translator->trans($type, [], 'flare_filter');
        }

        \asort($options, \SORT_NATURAL);

        return $options;
    }

    /**
     * The controls producing the value class of the edited filter's element; blank means intrinsic.
     *
     * @return array<string, string>
     */
    #[AsCallback(self::TABLE_NAME, 'fields.control.options')]
    public function getFieldOptions_control(DataContainer $dc): array
    {
        $type = (string) (DcaHelper::rowOf($dc)['type'] ?? '');
        $valueClass = $this->filterElementRegistry->getAttribute($type)?->value;

        if ($valueClass === null) {
            return [];
        }

        $options = [];

        foreach ($this->filterControlRegistry->findNames($valueClass) as $name) {
            $options[$name] = $GLOBALS['TL_LANG'][self::TABLE_NAME]['control_options'][$name] ?? $name;
        }

        return $options;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPublished.options')]
    public function getFieldOptions_fieldBool(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition): bool =>
                ($definition['inputType'] ?? null) === 'checkbox',
        );
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldStart.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldStop.options')]
    public function getFieldOptions_fieldDatim(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition): bool =>
                ($definition['inputType'] ?? null) === 'text' && ($definition['eval']['rgxp'] ?? null) === 'datim',
        );
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPid.options')]
    public function getFieldOptions_fieldPid(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions($dc, DcaFieldFilter::pid(...));
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPtable.options')]
    public function getFieldOptions_fieldPtable(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions($dc, DcaFieldFilter::ptable(...));
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldGeneric.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_columnsGeneric.options')]
    public function getFieldOptions_fieldGeneric(DataContainer $dc): array
    {
        /** @var ?FilterModel $filterModel */
        $filterModel = DcaHelper::modelOf($dc);
        if (!$filterModel) {
            return DcaHelper::getFieldOptions($dc);
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

        $list = $this->listFactory->createFromListModel($listModel)->build();
        $listExecutionContext = $this->listExecutionContextFactory->create($list);

        $table = $listExecutionContext->tableAliasRegistry
            ->getTable($filterModel->targetAlias ?: TableAliasRegistry::ALIAS_MAIN);

        return DcaHelper::getFieldOptions($table);
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_tablePtable.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_groupWhitelistParents.fields.tablePtable.options')]
    public function getFieldOptions_tablePtable(DataContainer $dc): array
    {
        $db = $this->contaoFramework->createInstance(Database::class);

        if (!$db || !$tables = $db->listTables()) {
            return [];
        }

        $tables = \array_filter($tables, $db->tableExists(...));
        return \array_combine($tables, $tables) ?: [];
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_whitelistParents.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_groupWhitelistParents.fields.whitelistParents.options')]
    public function getOptions_whitelistParents(DataContainer $dc): array
    {
        [$filterModel, $listModel] = $this->filterContainer->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return [];
        }

        $inferrer = new PtableInferrer($filterModel, $listModel->dc);

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

        if ($field !== 'elm_groupWhitelistParents' || $groupField !== 'whitelistParents') {
            return [];
        }

        if (!$savedGroups = StringUtil::deserialize($filterModel->elm_groupWhitelistParents ?? '')) {
            return [];
        }

        if (!$group = $savedGroups[(int) $index] ?? null) {
            return [];
        }

        $ptable = $group['tablePtable'] ?? null;

        return DcaHelper::getArchiveOptions($ptable);
    }

    #[AsCallback(self::TABLE_NAME, 'fields.ctl_formatLabel.options')]
    public function getOptions_formatLabel(DataContainer $dc): array
    {
        return $this->getFormatOptions('format.', 'ctl_formatLabel_custom');
    }

    /**
     * The per-group label format of the parent whitelist groups (element-owned, §4.1).
     */
    #[AsCallback(self::TABLE_NAME, 'fields.elm_groupWhitelistParents.fields.formatLabel.options')]
    public function getOptions_groupFormatLabel(DataContainer $dc): array
    {
        return $this->getFormatOptions('format.', 'elm_groupWhitelistParents_.formatLabel_custom');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.ctl_formatEmptyOption.options')]
    public function getOptions_formatEmptyOption(DataContainer $dc): array
    {
        return $this->getFormatOptions('empty_option.', 'ctl_formatEmptyOption_custom');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_configureStart.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_configureStop.options')]
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
            return [TableAliasRegistry::ALIAS_MAIN => TableAliasRegistry::ALIAS_MAIN];
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

        $list = $this->listFactory->createFromListModel($listModel)->build();

        $context = $this->listExecutionContextFactory->create($list);
        $tables = $context->tableAliasRegistry->getTables();
        $options = [];

        foreach ($tables as $alias => $table)
        {
            if ($context->tableAliasRegistry->isTableAliasHidden($alias)) {
                continue;
            }

            $options[$alias] = \sprintf('%s [%s]', $alias, $table);
        }

        return $options;
    }

    /**
     * @param string $prefix         The translation key prefix of the offered formats in the `flare_form` domain.
     * @param string $customLabelKey The `tl_flare_filter` language key of the "custom" option's label.
     */
    public function getFormatOptions(string $prefix, string $customLabelKey): array
    {
        if (!$this->translator instanceof TranslatorBagInterface) {
            return [];
        }

        $catalogue = $this->translator->getCatalogue();
        $labels = $catalogue->all('flare_form');

        $options = [];

        foreach ($labels as $key => $label)
        {
            if (!\str_starts_with($key, $prefix)) {
                continue;
            }

            $options[$key] = $label . ' [' . $key . ']';
        }

        unset($options['custom']);

        \asort($options);

        /** @noinspection PhpTranslationDomainInspection */
        $customLabel = $this->translator->trans("tl_flare_filter.{$customLabelKey}", [], 'contao_tl_flare_filter');

        return ['custom' => $customLabel] + $options;
    }
}
