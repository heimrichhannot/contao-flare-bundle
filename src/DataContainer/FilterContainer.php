<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilterContainer
{
    public const TABLE_NAME = 'tl_flare_filter';

    protected array $dcTableCache = [];

    public function __construct(
        private readonly FilterElementRegistry $filterElementRegistry,
        private readonly RequestStack $requestStack,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.field_published.options')]
    public function getFieldOptions_fieldBool(?DataContainer $dc): array
    {
        return $this->getFieldOptions(
            $dc,
            static fn(string $table, string $field, array $definition) =>
                ($definition['inputType'] ?? null) === 'checkbox'
        );
    }

    #[AsCallback(self::TABLE_NAME, 'fields.field_start.options')]
    #[AsCallback(self::TABLE_NAME, 'fields.field_stop.options')]
    public function getFieldOptions_fieldAny(?DataContainer $dc): array
    {
        return $this->getFieldOptions($dc);
    }

    #[AsCallback(self::TABLE_NAME, 'fields.field_start.load')]
    public function onLoadField_field_start(mixed $value, DataContainer $dc): string
    {
        if ($value) {
            return $value;
        }

        return $this->getColumnNameIfAvailable($dc, 'start') ?? '';
    }

    #[AsCallback(self::TABLE_NAME, 'fields.field_stop.load')]
    public function onLoadField_field_stop(mixed $value, DataContainer $dc): string
    {
        if ($value) {
            return $value;
        }

        return $this->getColumnNameIfAvailable($dc, 'stop') ?? '';
    }

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

    protected function getListDCTableFromDataContainer(?DataContainer $dc): ?string
    {
        if (!$dc
            || !($row = $dc?->activeRecord?->row())
            || !($pid = $row['pid'] ?? null))
        {
            return null;
        }

        if (isset($this->dcTableCache[$pid])) {
            return $this->dcTableCache[$pid];
        }

        if (!($list = ListModel::findByPk($pid))
            || !($table = $list->dc))
        {
            return null;
        }

        return $this->dcTableCache[$pid] = $table;
    }

    protected function getColumnNameIfAvailable(DataContainer $dc, string $column): ?string
    {
        if (!$table = $this->getListDCTableFromDataContainer($dc)) {
            return null;
        }

        Controller::loadDataContainer($table);

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'][$column])) {
            return null;
        }

        return $table . '.' . $column;
    }

    public function getFieldOptions(?DataContainer $dc, ?callable $predicate = null): array
    {
        if (!$table = $this->getListDCTableFromDataContainer($dc)) {
            return [];
        }

        Controller::loadDataContainer($table);

        $options = [];
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] ?? [] as $field => $definition)
        {
            if ($predicate !== null && !$predicate($table, $field, $definition)) {
                continue;
            }

            $key = $table . '.' . $field;
            $options[$key] = $key;
        }

        \ksort($options);

        return [$table => $options];
    }
}