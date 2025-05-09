<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilterContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_filter';
    public const CALLBACK_PREFIX = 'filter';

    public function __construct(
        private readonly ContaoFramework       $contaoFramework,
        private readonly FilterElementRegistry $filterElementRegistry,
        private readonly FlareCallbackRegistry $callbackRegistry,
        private readonly RequestStack          $requestStack,
        private readonly TranslationManager    $translationManager,
        private readonly TranslatorInterface   $translator,
    ) {}

    /* ============================= *
     *  CALLBACK HANDLING            *
     * ============================= */
    // <editor-fold desc="Callback Handling">

    /**
     * @throws \RuntimeException
     */
    public function handleFieldOptions(?DataContainer $dc, string $target): array
    {
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return [];
        }

        $namespace = static::CALLBACK_PREFIX . '.' . $filterModel->alias;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];

        return CallbackHelper::firstReturn($callbacks, [], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]) ?? [];
    }

    /**
     * @throws \RuntimeException
     */
    public function handleLoadField(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        return $this->handleValueCallback($value, $dc, $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleSaveField(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        return $this->handleValueCallback($value, $dc, $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleValueCallback(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return $value;
        }

        $namespace =  static::CALLBACK_PREFIX . '.' . $filterModel->alias;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];

        return CallbackHelper::firstReturn($callbacks, [$value], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]) ?? $value;
    }

    /**
     * @param DataContainer|null $dc
     * @return array{FilterModel, ListModel}|array{null, null}
     */
    public function getModelsFromDataContainer(?DataContainer $dc, bool $ignoreType = false): array
    {
        try
        {
            if (($id = $dc?->id)
                && ($filterModel = FilterModel::findByPk($id))
                && ($ignoreType || $filterModel->alias)
                && ($listModel = $filterModel->getRelated('pid')))
            {
                return [$filterModel, $listModel];
            }
        }
        catch (\Throwable) {}

        return [null, null];
    }

    // </editor-fold>

    /* ============================= *
     *  LOAD AND SAVE                *
     * ============================= */
    // <editor-fold desc="Load and Save">

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.save')]
    public function onLoadField_fieldPublished(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'published', '');
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.save')]
    public function onLoadField_fieldStart(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'start', '');
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.save')]
    public function onLoadField_fieldStop(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'stop', '');
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.save')]
    public function onLoadField_fieldPid(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'pid', '');
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.save')]
    public function onLoadField_fieldPtable(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'ptable', '');
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.load')]
    public function onLoadField_intrinsic(mixed $value, DataContainer $dc): bool
    {
        $value = (bool) $value;

        $request = $this->requestStack->getCurrentRequest();
        if ($request->getMethod() === 'POST' && $request->request->get('FORM_SUBMIT') === self::TABLE_NAME)
        {
            // do not disable intrinsic field if form is being submitted
            // otherwise the save callback will not be called
            return $value;
        }

        if (!$row = DcaHelper::currentRecord($dc)) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired())
        {
            $eval = &$GLOBALS['TL_DCA'][self::TABLE_NAME]['fields']['intrinsic']['eval'];

            $eval['disabled'] = true;

            return true;
        }

        return $value;
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.save')]
    public function onSaveField_intrinsic(mixed $value, DataContainer $dc): mixed
    {
        if ($value || !$row = DcaHelper::currentRecord($dc)) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired()) {
            return '1';
        }

        return $value;
    }

    // </editor-fold>

    /* ============================= *
     *  CONFIG                       *
     * ============================= */
    // <editor-fold desc="Config">

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'config.onsubmit')]
    public function onSubmit_whichPtable(DataContainer $dc): void
    {
        // ignore type because the type is not updated yet
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc, ignoreType: true);

        try
        {
            $inferrer = new PtableInferrer($filterModel, $listModel);

            $inferrer->infer();

            if (!$inferrer->isAutoInferable())
            {
                $filterModel->whichPtable_disableAutoOption();
            }
        }
        catch (InferenceException) {}
    }

    // </editor-fold>

    /* ============================= *
     *  OPTIONS                      *
     * ============================= */
    // <editor-fold desc="Options">

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getFieldOptions_type(): array
    {
        $options = [];

        foreach ($this->filterElementRegistry->all() as $alias => $filterElement)
        {
            $options[$alias] = $this->translationManager->filterElement($alias);
        }

        return $options;
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.options')]
    public function getFieldOptions_fieldBool(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'checkbox'
        );
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.options')]
    public function getFieldOptions_fieldDatim(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'text' && ($definition['eval']['rgxp'] ?? null) === 'datim'
        );
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.options')]
    public function getFieldOptions_fieldPid(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static function(string $table, string $field, array $definition) {
                if (\str_contains($field, 'pid')
                    || \is_array($definition['relation'] ?? null)
                    || \is_string($definition['foreignKey'] ?? null)) {
                    return true;
                }

                return DcaHelper::testSQLType($definition['sql'] ?? null, 'int');
            }
        );
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.options')]
    public function getFieldOptions_fieldPtable(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static function(string $table, string $field, array $definition) {
                if (\str_contains($field, 'ptable')) {
                    return true;
                }

                if (($definition['inputType'] ?? null) === 'text'
                    && !DcaHelper::testSQLType($definition['sql'] ?? null, 'int')) {
                    return true;
                }

                return DcaHelper::testSQLType($definition['sql'] ?? null, 'text');
            }
        );
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'fields.fieldGeneric.options')]
    public function getFieldOptions_fieldGeneric(DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions($dc);
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
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
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

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

    // </editor-fold>

    /* ============================= *
     *  LIST                         *
     * ============================= */
    // <editor-fold desc="List">

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

    /**
     * @internal For internal use only. Do not call this method directly.
     */
    #[AsCallback(self::TABLE_NAME, 'list.sorting.child_record')]
    public function listLabelLabel(array $row): string
    {
        $key = $row['published'] ? 'published' : 'unpublished';

        $title = StringUtil::specialchars($row['title'] ?? '');

        if ($type = $row['type'] ?? null)
        {
            $typeLabel = StringUtil::specialchars($this->translationManager->filterElement($type));
        }

        $typeLabel ??= 'N/A';

        $html = "<div class=\"cte_type $key\">[$typeLabel]</div>";
        $html .= $title ? "<div><strong>$title</strong></div>" : '';

        return $html;
    }

    // </editor-fold>
}