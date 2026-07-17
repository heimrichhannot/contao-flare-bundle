<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Controller;
use Contao\Message;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Enum\BoolMode;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Type\BooleanFilterType;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class BooleanFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_bool';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('preselect')->default(null)->allowedTypes('bool', 'null');
        $resolver->define('mode')->default(BoolMode::BINARY)->allowedTypes(BoolMode::class);
        $resolver->define('binary_choices')->default(BoolBinaryChoices::NULL_TRUE)->allowedTypes(BoolBinaryChoices::class);
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('field', $model->fieldGeneric ?: null)
            ->set('preselect', $this->normalizeValue($model->preselect))
            ->set('mode', BoolMode::tryFrom((string) $model->boolMode) ?? BoolMode::BINARY)
            ->set('binary_choices', BoolBinaryChoices::tryFrom((string) $model->boolBinaryChoices) ?? BoolBinaryChoices::NULL_TRUE)
            ->set('label', $model->label ?: $model->title ?: null);
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
        if ($context->config['intrinsic']) {
            return;
        }

        $builder->single(CheckboxType::class, [
            'label' => $context->config['label'] ?? 'CBX',
            'required' => false,
        ]);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        $config = $context->config;

        if (!$targetField = $config['field']) {
            $builder->abort();
        }

        $value = $config['intrinsic']
            ? $config['preselect']
            : $this->resolveRuntimeValue($values[FilterContext::SINGLE_VALUE] ?? null, $config);

        if ($value === null) {
            return;
        }

        $builder->add(BooleanFilterType::class, [
            'field' => $targetField,
            'value' => $value,
        ]);
    }

    private function resolveRuntimeValue(mixed $value, array $config): ?bool
    {
        $choices = $config['mode'] === BoolMode::BINARY ? $config['binary_choices'] : null;

        return $this->normalizeValue($value, $choices) ?? $config['preselect'];
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

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
    {
        $intrinsic = (bool) $context->filterModel?->intrinsic;

        $dca->palette($intrinsic
            ? '{filter_legend},fieldGeneric,preselect'
            : '{filter_legend},fieldGeneric,label,boolMode,preselect');

        $preselectOptions = [
            'null' => 'flare.bool_preselect.null',
            'true' => 'flare.bool_preselect.true',
            'false' => 'flare.bool_preselect.false',
        ];

        if ($intrinsic) {
            unset($preselectOptions['null']);
        }

        $dca->field('preselect')
            ->inputType('select')
            ->eval(['includeBlankOption' => false, 'chosen' => false])
            ->options($preselectOptions);

        $dca->field('fieldGeneric')
            ->options(fn (): array => $this->getFieldGenericOptions($context->getTargetTable()));

        if ($context->filterModel?->boolMode === BoolMode::TERNARY->value) {
            Message::addError('The ternary mode is currently not supported by the boolean filter element. Please use the binary mode instead.');
        }
    }

    protected function getFieldGenericOptions(string $targetTable): array
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
}
