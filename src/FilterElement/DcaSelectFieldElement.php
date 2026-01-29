<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\Controller;
use Contao\DataContainer;
use Contao\StringUtil;
use Contao\System;
use HeimrichHannot\FlareBundle\Contract\Config\InScopeConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\InScopeContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(
    type: self::TYPE,
    formType: ChoiceType::class,
)]
class DcaSelectFieldElement extends AbstractFilterElement implements HydrateFormContract, InScopeContract
{
    public const TYPE = 'flare_dcaSelectField';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $options = $this->getOptions($inv->list, $inv->filter) ?? [];

        if ($inv->filter->isIntrinsic())
        {
            if (!$preselect = $this->getPreselectValue($inv->filter))
            {
                return;
            }

            if (!$selected = $options[$preselect] ?? null)
            {
                $qb->abort();
            }
        }

        if (!$selected ??= $inv->getValue())
        {
            return;
        }

        $selected = \array_values((array) $selected);

        if (!\count($selected))
        {
            return;
        }

        if (!$targetField = $inv->filter->fieldGeneric)
        {
            $qb->abort();
        }

        if (!$options)
        {
            $qb->abort();
        }

        $dcaOptionsField = $this->getOptionsField($inv->list, $inv->filter) ?? [];
        $isMultiple = $dcaOptionsField['eval']['multiple'] ?? false;

        if (\count($selected) === 1)
        {
            if (!$value = \array_search($selected[0], $options, true))
            {
                $qb->abort();
            }

            if ($isMultiple)
            {
                $qb->whereInSerialized($value, $targetField);
            }
            else
            {
                $qb->where($qb->expr()->eq($qb->column($targetField), ':value'))
                    ->setParameter('value', $value);
            }

            return;
        }

        if (\count(\array_unique($options)) !== \count($options))
            // options are not unique, cannot flip
        {
            throw new FilterException(\sprintf(
                'The options for the DCA select field %s.%s must be unique.',
                $context->getListModel()->dc,
                $targetField,
            ));
        }

        $options = \array_flip($options);
        $validOptions = [];

        foreach ($selected as $value)
        {
            if ($key = $options[$value] ?? null) {
                $validOptions[] = $key;
            }
        }

        if (!\count($validOptions))
            // of the submitted values, none is valid
        {
            $qb->abort();
        }

        if ($isMultiple)
        {
            $qb->whereInSerialized($validOptions, $targetField);
        }
        else
        {
            $qb->where($qb->expr()->in($qb->column($targetField), ':values'))
                ->setParameter('values', $validOptions);
        }
    }

    public function isInScope(InScopeConfig $config): bool
    {
        return $config->getFilterModel()->intrinsic || $config->getContentContext()->isList();
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $palette = '{filter_legend},fieldGeneric,isMultiple,preselect';

        if (!$config->getFilterModel()->intrinsic) {
            $palette .= ';{form_legend},isExpanded,isMandatory,label,placeholder';
        }

        return $palette;
    }

    public function getPreselectValue(FilterDefinition $filter): mixed
    {
        return $filter->isMultiple
            ? StringUtil::deserialize($filter->preselect ?: null)
            : $filter->preselect;
    }

    public function hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void
    {
        if ($field->isSubmitted()) {
            return;
        }

        if (!$preselect = $this->getPreselectValue($filter)) {
            return;
        }

        $options = $this->getOptions($list, $filter) ?? [];

        if (!\is_array($preselect))
        {
            if (!\is_scalar($preselect)) {
                $field->setData($preselect);
                return;
            }

            if (!$option = $options[$preselect] ?? null) {
                return;
            }

            $field->setData($option);
            return;
        }

        $data = [];

        foreach ($preselect as $value)
        {
            if (!\is_scalar($value)) {
                $data[] = $value;
                continue;
            }

            if ($option = $options[$value] ?? null) {
                $data[] = $option;
            }
        }

        $field->setData($data);
    }

    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void
    {
        $list = $event->listDefinition;
        $filter = $event->filterDefinition;

        $emptyPlaceholder = $filter->isMandatory ? 'empty_option.prompt' : 'empty_option.no_selection';

        $event->options['multiple'] = (bool) $filter->isMultiple;
        $event->options['expanded'] = (bool) $filter->isExpanded;
        $event->options['required'] = (bool) $filter->isMandatory;
        $event->options['placeholder'] = $filter->placeholder ?: $emptyPlaceholder;

        if ($filter->label) {
            $event->options['label'] = $filter->label;
        }

        if (\is_null($options = $this->getOptions($list, $filter))) {
            return;
        }

        $choices = $event->getChoicesBuilder()->enable();

        foreach ($options as $value => $label) {
            $choices->add($value, $label);
        }
    }

    #[AsFilterCallback(self::TYPE, 'config.onload')]
    public function onLoadConfig(FilterModel $filterModel): void
    {
        $table = FilterModel::getTable();
        $fields = &$GLOBALS['TL_DCA'][$table]['fields'];

        ###> fieldGeneric
        $field = &$fields['fieldGeneric'];
        $field['eval']['alwaysSave'] = true;
        $field['eval']['submitOnChange'] = true;
        ###< fieldGeneric

        ###> isMultiple
        $field = &$fields['isMultiple'];
        $field['eval']['submitOnChange'] = true;
        ###< isMultiple

        ###> preselect
        $field = &$fields['preselect'];
        $field['inputType'] = 'select';
        $field['eval']['includeBlankOption'] = true;
        $field['eval']['multiple'] = $filterModel->isMultiple;
        $field['eval']['chosen'] = true;
        ###< preselect
    }

    #[AsFilterCallback(self::TYPE, 'fields.fieldGeneric.options')]
    public function getFieldGenericOptions(ListModel $listModel): array
    {
        Controller::loadDataContainer($listModel->dc);

        if (!isset($GLOBALS['TL_DCA'][$listModel->dc]['fields'])) {
            return [];
        }

        // find all fields with a type of select
        $options = [];
        foreach ($GLOBALS['TL_DCA'][$listModel->dc]['fields'] as $name => $field)
        {
            if ('select' === ($field['inputType'] ?? null)) {
                $options[$name] = $listModel->dc . '.' . $name;
            }
        }

        return $options;
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.options')]
    public function getPreselectOptions(ListModel $listModel, FilterModel $filterModel): array
    {
        if (!$field = $this->getOptionsField($listModel, $filterModel)) {
            return [];
        }

        if (!($preselectField = &$GLOBALS['TL_DCA'][FilterModel::getTable()]['fields']['preselect'])) {
            return [];
        }

        $preselectField['reference'] = $field['reference'] ?? [];
        $preselectField['eval']['multiple'] = (bool) $filterModel->isMultiple;

        return $this->tryGetOptionsFromField($listModel, $field) ?? [];
    }

    public function getOptions(ListSpecification $list, FilterDefinition $filter): ?array
    {
        $optionsField = $this->getOptionsField($list, $filter) ?? [];
        $options = $this->tryGetOptionsFromField($list, $optionsField);

        if (!\is_array($options))
        {
            return null;
        }

        if (\array_is_list($options))
        {
            $options = \array_combine($options, $options);
        }

        if ($reference = $optionsField['reference'] ?? [])
        {
            foreach ($options as $k => $v)
            {
                $options[$k] = $reference[$v] ?? $reference[$k] ?? $v;
            }
        }

        return $options;
    }

    public function getOptionsField(ListModel|ListSpecification $list, FilterModel|FilterDefinition $filter): ?array
    {
        Controller::loadLanguageFile($list->dc);
        Controller::loadDataContainer($list->dc);

        return $GLOBALS['TL_DCA'][$list->dc]['fields'][$filter->fieldGeneric] ?? null;
    }

    protected function tryGetOptionsFromField(ListModel|ListSpecification $list, array $optionsField): ?array
    {
        if (\is_array($options = $optionsField['options'] ?? null))
        {
            return $options;
        }

        if ($optionsCallback = $optionsField['options_callback'] ?? null)
        {
            $dataContainer = $this->mockDataContainerObject($list->dc);

            if (\is_string($optionsCallback) && \str_contains($optionsCallback, '::'))
            {
                [$class, $method] = \explode('::', $optionsCallback, 2);
                $optionsCallback = [$class, $method];
            }

            if (\is_array($optionsCallback) && \count($optionsCallback) === 2)
            {
                $class = $optionsCallback[0] ?? null;
                $method = $optionsCallback[1] ?? null;

                if (!\class_exists($class) || !\method_exists($class, $method)) {
                    return null;
                }

                if (!$service = System::importStatic($class)) {
                    return null;
                }

                $options = $service->{$method}($dataContainer);
            }

            if (!\is_array($optionsCallback) && \is_callable($optionsCallback))
            {
                $options = $optionsCallback($dataContainer);
            }
        }

        if (!\is_array($options)) {
            return null;
        }

        return $options;
    }

    protected function mockDataContainerObject(string $table): DataContainer
    {
        return new class($table) extends DataContainer {
            /**
             * @noinspection MagicMethodsValidityInspection
             * @noinspection PhpMissingParentConstructorInspection
             */
            public function __construct(string $table)
            {
                if ($table)
                {
                    $this->strTable = $table;
                }
            }

            public function getPalette(): string
            {
                return '';
            }

            protected function save($varValue): void
            {
                // do nothing
            }
        };
    }
}