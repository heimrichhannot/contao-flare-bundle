<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\Controller;
use Contao\Message;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\FlareBundle\Contract\Config\InScopeConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\InScopeContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Enum\BoolMode;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

#[AsFilterElement(
    alias: self::TYPE,
    palette: '{filter_legend},fieldGeneric,label,boolMode,preselect',
    formType: CheckboxType::class
)]
class BooleanElement extends AbstractFilterElement implements InScopeContract, FormTypeOptionsContract
{
    public const TYPE = 'flare_bool';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();

        if (!$targetField = $filterModel->fieldGeneric) {
            $qb->abort();
        }

        $mode = BoolMode::tryFrom($filterModel->boolMode ?: '') ?? BoolMode::BINARY;

        if ($mode === BoolMode::BINARY) {
            $boolBinaryChoices = BoolBinaryChoices::tryFrom($filterModel->boolBinaryChoices ?: '') ?? BoolBinaryChoices::NULL_TRUE;
        }

        // todo: refactor

        $value = $this->normalizeValue($context->getSubmittedData(), $boolBinaryChoices ?? null)
            ?? $this->normalizeValue($filterModel->preselect);

        if ($value === null) {
            return;
        }

        $qField = $qb->quoteIdentifier($targetField);

        $qb->where($qb->expr()->or(
            $qb->expr()->eq($qField, ':bool'),
            $qb->expr()->eq($qField, ':numeric'),
            $qb->expr()->eq($qField, ':string'),
        ))
            ->setParameter('bool', $value, ParameterType::BOOLEAN)
            ->setParameter('numeric', $value ? 1 : 0, ParameterType::INTEGER)
            ->setParameter('string', $value ? '1' : '', ParameterType::STRING)
        ;
    }

    public function isInScope(InScopeConfig $config): bool
    {
        $filterModel = $config->getFilterModel();

        if ($filterModel->intrinsic) {
            return $this->normalizeValue($filterModel->preselect) !== null;
        }

        return $config->getContentContext()->isList();
    }

    public function normalizeValue(mixed $value, ?BoolBinaryChoices $choices = null): ?bool
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
        }

        if ($value === null || $value === '' || $value === 'null'
            || ($choices === BoolBinaryChoices::NULL_TRUE && !$value))
        {
            return null;
        }

        return \filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    #[AsFilterCallback(self::TYPE, 'config.onload')]
    public function onLoadConfig(FilterModel $filterModel): void
    {
        $table = FilterModel::getTable();
        $fields = &$GLOBALS['TL_DCA'][$table]['fields'];

        ###> preselect
        $field = &$fields['preselect'];
        $field['inputType'] = 'select';
        $field['eval']['includeBlankOption'] = false;
        $field['eval']['chosen'] = false;
        $field['options'] = [
            'null' => 'flare.bool_preselect.null',
            'true' => 'flare.bool_preselect.true',
            'false' => 'flare.bool_preselect.false',
        ];
        ###< preselect

        if ($filterModel->boolMode === BoolMode::TERNARY->value) {
            Message::addError('The ternary mode is currently not supported by the boolean filter element. Please use the binary mode instead.');
        }
    }

    #[AsFilterCallback(self::TYPE, 'fields.fieldGeneric.options')]
    public function getFieldGenericOptions(ListModel $listModel): array
    {
        Controller::loadDataContainer($listModel->dc);

        if (!isset($GLOBALS['TL_DCA'][$listModel->dc]['fields'])) {
            return [];
        }

        $cbx = 'Checkbox';
        $non = 'Non-Checkbox';

        $options = [
            $cbx => [], // checkbox fields
            $non => [], // non-checkbox fields
        ];

        foreach ($GLOBALS['TL_DCA'][$listModel->dc]['fields'] as $name => $field)
        {
            $group = ('checkbox' === ($field['inputType'] ?? null)) ? $cbx : $non;
            $options[$group][$name] = $listModel->dc . '.' . $name;
        }

        \asort($options[$cbx]);
        \asort($options[$non]);

        return $options;
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        return [
            'required' => false,
            'label' => $context->getFilterModel()->label ?: $context->getFilterModel()->title ?: 'CBX',
        ];
    }
}