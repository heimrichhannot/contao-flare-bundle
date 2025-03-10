<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\FlareBundle\Util\MethodInjector;
use HeimrichHannot\FlareBundle\Util\PtableInferrer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal For internal use only. API might change without notice.
 */
class FilterContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_filter';

    public function __construct(
        private readonly ContaoFramework       $contaoFramework,
        private readonly FilterElementRegistry $filterElementRegistry,
        private readonly FlareCallbackRegistry $callbackRegistry,
        private readonly RequestStack          $requestStack,
    ) {}

    /* ============================= *
     *  LOAD AND SAVE                *
     * ============================= */
    // <editor-fold desc="Load and Save">

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.save')]
    public function onLoadField_fieldPublished(mixed $value, DataContainer $dc): string
    {
        $x = $value ?: DcaHelper::tryGetColumnName($dc, 'published', '');
        return $x;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.save')]
    public function onLoadField_fieldStart(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'start', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.save')]
    public function onLoadField_fieldStop(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'stop', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.save')]
    public function onLoadField_fieldPid(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'pid', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.save')]
    public function onLoadField_fieldPtable(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'ptable', '');
    }

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

        if (!$row = $dc->activeRecord?->row()) {
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

    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.save')]
    public function onSaveField_intrinsic(mixed $value, DataContainer $dc): mixed
    {
        if ($value || !$row = $dc->activeRecord?->row()) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired()) {
            return '1';
        }

        return $value;
    }

    public function onLoadField(mixed $value, DataContainer $dc): mixed
    {
        // TODO: Implement onLoadField() method.
        return $value;
    }

    public function onSaveField(mixed $value, DataContainer $dc): mixed
    {
        // TODO: Implement onSaveField() method.
        return $value;
    }

    #[AsCallback(self::TABLE_NAME, 'config.onsubmit')]
    public function onSubmit_whichPtable(DataContainer $dc): void
    {
        if (!$dc->id
            || $dc->table !== self::TABLE_NAME
            || !($filterModel = FilterModel::findByPk($dc->id))
            || !($listModel = $filterModel->getRelated('pid')))
        {
            return;
        }

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

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getFieldOptions_type(): array
    {
        $options = [];

        foreach ($this->filterElementRegistry->all() as $alias => $filterElement)
        {
            $service = $filterElement->getService();
            $options[$alias] = \class_implements($service, TranslatorInterface::class)
                ? $service->trans($alias)
                : $alias;
        }

        return $options;
    }

    #[AsFilterCallback('default', 'fields.fieldPublished.options')]
    public function getFieldOptions_fieldBool(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'checkbox'
        );
    }

    #[AsFilterCallback('default', 'fields.fieldStart.options')]
    #[AsFilterCallback('default', 'fields.fieldStop.options')]
    public function getFieldOptions_fieldDatim(?DataContainer $dc): array
    {
        return DcaHelper::getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'text' && ($definition['eval']['rgxp'] ?? null) === 'datim'
        );
    }

    #[AsFilterCallback('default', 'fields.fieldPid.options')]
    public function getFieldOptions_fieldPid(?DataContainer $dc): array
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

    #[AsFilterCallback('default', 'fields.fieldPtable.options')]
    public function getFieldOptions_fieldPtable(?DataContainer $dc): array
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

    #[AsFilterCallback('default', 'fields.tablePtable.options')]
    public function getFieldOptions_tablePtable(?DataContainer $dc): array
    {
        $db = $this->contaoFramework->createInstance(Database::class);

        if (!$tables = $db?->listTables()) {
            return [];
        }

        $tables = \array_filter($tables, static fn(string $table) => $db->tableExists($table));
        return \array_combine($tables, $tables);
    }

    /**
     * @throws \ReflectionException
     */
    public function getFieldOptions(?DataContainer $dc): array
    {
        if (!$dc?->id
            || !($filterModel = FilterModel::findByPk($dc->id))?->type
            || !($listModel = $filterModel->getRelated('pid')))
        {
            return [];
        }

        $prefix = 'filter.';

        $namespace = $prefix . $filterModel->type;
        $target = "fields.{$dc->field}.options";

        $callbacks = \array_merge(
            $this->callbackRegistry->getSorted($namespace, $target) ?? [],
            $this->callbackRegistry->getSorted($prefix . 'default', $target) ?? []
        );

        foreach ($callbacks as $callbackConfig)
        {
            $method = $callbackConfig->getMethod();
            $service = $callbackConfig->getService();

            if (!\method_exists($service, $method)) {
                continue;
            }

            $options = MethodInjector::invoke($service, $method, [
                FilterModel::class => $filterModel,
                ListModel::class  => $listModel,
                DataContainer::class  => $dc,
            ]);

            if (isset($options)) {
                return $options;
            }
        }

        return [];
    }

    // </editor-fold>

    /* ============================= *
     *  LABELS                       *
     * ============================= */
    // <editor-fold desc="Labels">

    #[AsCallback(self::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

    #[AsCallback(self::TABLE_NAME, 'list.sorting.child_record')]
    public function listLabelLabel(array $row): string
    {
        $key = $row['published'] ? 'published' : 'unpublished';

        $title = StringUtil::specialchars($row['title'] ?? '');

        if ($type = $row['type'] ?? null)
        {
            $filterElement = $this->filterElementRegistry->get($type);
            $service = $filterElement?->getService();

            if ($service instanceof TranslatorInterface)
            {
                $typeLabel = $service->trans($row['type'] ?? '');
            }

            $typeLabel = StringUtil::specialchars($typeLabel ?? $type);
        }

        $typeLabel ??= 'N/A';

        $html = "<div class=\"cte_type $key\">$typeLabel</div>";
        $html .= $title ? "<div><strong>$title</strong></div>" : '';

        return $html;
    }

    // </editor-fold>
}