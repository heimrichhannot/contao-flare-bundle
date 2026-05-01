<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\Controller;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Enum\BoolMode;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Type\BooleanFilterType;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},fieldGeneric,preselect',
    formType: CheckboxType::class,
    isTargeted: true,
)]
class BooleanElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public const TYPE = 'flare_bool';

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void
    {
        $filter = $invocation->filter;

        if (!$targetField = $filter->fieldGeneric) {
            $builder->abort();
        }

        $value = $filter->isIntrinsic()
            ? $this->getIntrinsicValue($invocation->list, $filter)
            : $this->processRuntimeValue($invocation->getValue(), $invocation->list, $filter);

        if ($value === null) {
            return;
        }

        $builder->add(BooleanFilterType::class, [
            'field' => $targetField,
            'value' => $value,
        ]);
    }

    public function getIntrinsicValue(ListSpecification $list, ConfiguredFilter $filter): bool
    {
        return (bool) $this->normalizeValue($filter->preselect);
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, ConfiguredFilter $filter): ?bool
    {
        $mode = BoolMode::tryFrom($filter->boolMode ?: '') ?? BoolMode::BINARY;

        $boolBinaryChoices = match ($mode) {
            BoolMode::BINARY =>
                BoolBinaryChoices::tryFrom($filter->boolBinaryChoices ?: '')
                ?? BoolBinaryChoices::NULL_TRUE,
            default => null,
        };

        return $this->normalizeValue($value, $boolBinaryChoices)
            ?? $this->normalizeValue($filter->preselect);
    }

    public function normalizeValue(mixed $value, ?BoolBinaryChoices $choices = null): ?bool
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
        }

        if ($value === null || $value === '' || $value === 'null')
        {
            return null;
        }

        if ($choices === BoolBinaryChoices::NULL_TRUE && !$value)
        {
            return null;
        }

        return \filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
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

        if ($filterModel->intrinsic) {
            unset($field['options']['null']);
        }

        ###< preselect

        if ($filterModel->boolMode === BoolMode::TERNARY->value) {
            Message::addError('The ternary mode is currently not supported by the boolean filter element. Please use the binary mode instead.');
        }
    }

    #[AsFilterCallback(self::TYPE, 'fields.fieldGeneric.options')]
    public function getFieldGenericOptions(string $targetTable): array
    {
        Controller::loadDataContainer($targetTable);

        if (!isset($GLOBALS['TL_DCA'][$targetTable]['fields'])) {
            return [];
        }

        $cbx = 'Checkbox';
        $non = 'Non-Checkbox';

        $options = [
            $cbx => [], // checkbox fields
            $non => [], // non-checkbox fields
        ];

        foreach ($GLOBALS['TL_DCA'][$targetTable]['fields'] as $name => $field)
        {
            $group = ('checkbox' === ($field['inputType'] ?? null)) ? $cbx : $non;
            $options[$group][$name] = $targetTable . '.' . $name;
        }

        \asort($options[$cbx]);
        \asort($options[$non]);

        return $options;
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $filter = $event->filter;
        $event->options['label'] = $filter->label ?: $filter->title ?: 'CBX';
        $event->options['required'] = false;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        if ($config->getFilterModel()->intrinsic) {
            return null;
        }

        return '{filter_legend},fieldGeneric,label,boolMode,preselect';
    }

    public static function define(
        ?string $targetField = null,
        ?bool $expectedValue = null,
    ): ConfiguredFilter {
        $definition = new ConfiguredFilter(
            type: static::TYPE,
            intrinsic: true,
        );

        $definition->fieldGeneric = $targetField;
        $definition->preselect = (string) (bool) $expectedValue;

        return $definition;
    }
}
