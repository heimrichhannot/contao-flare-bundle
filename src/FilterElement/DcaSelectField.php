<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\Controller;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\InScopeConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\InScopeContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(
    alias: self::TYPE,
    formType: ChoiceType::class,
)]
class DcaSelectField implements FormTypeOptionsContract, HydrateFormContract, PaletteContract, InScopeContract
{
    public const TYPE = 'flare_dcaSelectField';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        if (!$submittedData = $context->getSubmittedData()) {
            return;
        }

        $submittedData = \array_values((array) $submittedData);

        if (!\count($submittedData)) {
            return;
        }

        if (!$targetField = $context->getFilterModel()->fieldGeneric)
        {
            $qb->abort();
        }

        if (!$options = $this->getOptions($context->getListModel(), $context->getFilterModel()) ?? [])
        {
            $qb->abort();
        }

        if (\count($submittedData) === 1)
        {
            if (!$value = \array_search($submittedData[0], $options, true))
            {
                $qb->abort();
            }

            $qb->where($qb->expr()->eq($qb->column($targetField), ':value'))
                ->setParameter('value', $value);

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

        foreach ($submittedData as $value)
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

        $qb->where($qb->expr()->in($qb->column($targetField), ':values'))
            ->setParameter('values', $validOptions);
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

    public function hydrateForm(FilterContext $context, FormInterface $field): void
    {
        $filterModel = $context->getFilterModel();

        if ($preselect = $filterModel->isMultiple
            ? StringUtil::deserialize($filterModel->preselect ?: null)
            : $filterModel->preselect)
        {
            $field->setData($preselect);
        }
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        $listModel = $context->getListModel();
        $filterModel = $context->getFilterModel();

        $emptyPlaceholder = $filterModel->isMandatory ? 'empty_option.prompt' : 'empty_option.no_selection';

        $return = [
            'multiple' => (bool) $filterModel->isMultiple,
            'expanded' => (bool) $filterModel->isExpanded,
            'required' => (bool) $filterModel->isMandatory,
            'placeholder' => $filterModel->placeholder ?: $emptyPlaceholder,
        ];

        if ($filterModel->label) {
            $return['label'] = $filterModel->label;
        }

        $options = $this->getOptions($listModel, $filterModel);

        if (\is_null($options)) {
            return $return;
        }

        $choices->enable();

        foreach ($options as $value => $label)
        {
            $choices->add($value, $label);
        }

        return $return;
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

        return $field['options'] ?? [];
    }

    public function getOptions(ListModel $listModel, FilterModel $filterModel): ?array
    {
        $optionsField = $this->getOptionsField($listModel, $filterModel) ?? [];
        $reference = $optionsField['reference'] ?? [];

        if (!$options = $optionsField['options'] ?? null) {
            return null;
        }

        if (\array_is_list($options))
        {
            $options = \array_combine($options, $options);
        }

        if ($reference)
        {
            foreach ($options as $k => $v)
            {
                $options[$k] = $reference[$v] ?? $reference[$k] ?? $v;
            }
        }

        return $options;
    }

    public function getOptionsField(ListModel $listModel, FilterModel $filterModel): ?array
    {
        Controller::loadDataContainer($listModel->dc);

        return $GLOBALS['TL_DCA'][$listModel->dc]['fields'][$filterModel->fieldGeneric] ?? null;
    }
}