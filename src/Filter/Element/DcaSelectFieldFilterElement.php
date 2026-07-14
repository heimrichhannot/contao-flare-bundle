<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Controller;
use Contao\DataContainer;
use Contao\StringUtil;
use Contao\System;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\DcaSelectFilterType;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE)]
class DcaSelectFieldFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_dcaSelectField';

    public function __construct(
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('is_multiple')->default(false)->allowedTypes('bool');
        $resolver->define('is_expanded')->default(false)->allowedTypes('bool');
        $resolver->define('is_mandatory')->default(false)->allowedTypes('bool');
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('placeholder')->default(null)->allowedTypes('string', 'null');
        $resolver->define('preselect')->default(null);
    }

    protected function transformFilterModel(FilterModel $model, ConfigBuilder $config): void
    {
        $isMultiple = (bool) $model->isMultiple;
        $preselect = $model->preselect ?: null;

        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('field', $model->fieldGeneric ?: null)
            ->set('is_multiple', $isMultiple)
            ->set('is_expanded', (bool) $model->isExpanded)
            ->set('is_mandatory', (bool) $model->isMandatory)
            ->set('label', $model->label ?: null)
            ->set('placeholder', $model->placeholder ?: null)
            ->set('preselect', $isMultiple
                ? StringUtil::deserialize($preselect)
                : $preselect);
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $config = $context->config;

        if ($config['intrinsic']) {
            return;
        }

        $defaultPlaceholder = $config['is_mandatory'] ? 'empty_option.prompt' : 'empty_option.no_selection';

        $formOptions = [
            'label' => $config['label'] ?: false,
            'multiple' => $config['is_multiple'],
            'expanded' => $config['is_expanded'],
            'required' => $config['is_mandatory'],
            'placeholder' => $config['placeholder'] ?: $defaultPlaceholder,
        ];

        $options = $this->getOptions($context->list->dc, $config['field']);

        if (!\is_null($options))
        {
            $choicesBuilder = $this->choicesBuilderFactory->createChoicesBuilder()->enable();

            foreach ($options as $value => $label) {
                $choicesBuilder->add((string) $value, (string) $label);
            }

            $formOptions['choice_loader'] = $choicesBuilder->buildCallbackChoiceLoader();
            $formOptions['choice_label'] = $choicesBuilder->buildChoiceLabelCallback();
            $formOptions['choice_value'] = $choicesBuilder->buildChoiceValueCallback();

            $builder->setAttribute('flare.choices_builder', $choicesBuilder);
        }

        if (null !== $data = $this->buildPreselectData($config['preselect'], $options ?? [])) {
            $formOptions['data'] = $data;
        }

        $builder->add(FilterContext::FIELD_VALUE, ChoiceType::class, $formOptions);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        $config = $context->config;
        $options = $this->getOptions($context->list->dc, $config['field']) ?? [];

        $selected = $config['intrinsic']
            ? $config['preselect']
            : $this->normalizeSubmittedValue($data[FilterContext::FIELD_VALUE] ?? null, $options);

        if (!$selected) {
            return;
        }

        if (!$selected = \array_values((array) $selected)) {
            return;
        }

        if (!$options) {
            $builder->abort();
        }

        if (!$targetField = $config['field']) {
            $builder->abort();
        }

        $dcaOptionsField = $this->getOptionsField($context->list->dc, $config['field']) ?? [];
        $isMultiple = $dcaOptionsField['eval']['multiple'] ?? false;

        $builder->add(DcaSelectFilterType::class, [
            'field' => $targetField,
            'selected' => $selected,
            'valid_options' => $options,
            'is_multiple_dca_field' => (bool) $isMultiple,
        ]);
    }

    /**
     * Computes the initial choice data from the configured preselection, mirroring how the
     * form would present it: scalar preselect keys are mapped to their option labels.
     */
    private function buildPreselectData(mixed $preselect, array $options): mixed
    {
        if (!$preselect) {
            return null;
        }

        if (!\is_array($preselect))
        {
            if (!\is_scalar($preselect)) {
                return $preselect;
            }

            if (!$option = $options[$preselect] ?? null) {
                return null;
            }

            return (string) $option;
        }

        $data = [];

        foreach ($preselect as $value)
        {
            if (!\is_scalar($value)) {
                $data[] = $value;
                continue;
            }

            if ($option = $options[$value] ?? null) {
                $data[] = (string) $option;
            }
        }

        return $data;
    }

    /**
     * Maps submitted choice data (option labels) back to the option keys the filter query
     * expects, mirroring the choice value callback of the form's choices.
     */
    private function normalizeSubmittedValue(mixed $value, array $options): mixed
    {
        if (\is_null($value)) {
            return null;
        }

        $choices = [];
        foreach ($options as $key => $label) {
            $choices[(string) $key] = (string) $label;
        }

        $toKey = static function (mixed $choice) use ($choices): string {
            $key = \array_search($choice, $choices, true);
            return ($key === false) ? '' : (string) $key;
        };

        if (\is_array($value)) {
            return \array_map($toKey, $value);
        }

        return $toKey($value);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $intrinsic = (bool) $context->filterModel?->intrinsic;

        $palette = '{filter_legend},fieldGeneric,isMultiple,preselect';

        if (!$intrinsic) {
            $palette .= ';{form_legend},isExpanded,isMandatory,label,placeholder';
        }

        $dca->palette($palette);

        $dca->field('fieldGeneric')
            ->eval(['alwaysSave' => true, 'submitOnChange' => true])
            ->options(fn (): array => $this->getFieldGenericOptions($context->listModel->dc));

        $dca->field('isMultiple')
            ->eval(['submitOnChange' => true]);

        $preselect = $dca->field('preselect')
            ->inputType('select')
            ->eval([
                'includeBlankOption' => true,
                'multiple' => (bool) $context->filterModel?->isMultiple,
                'chosen' => true,
            ]);

        $table = $context->listModel->dc;

        if ($optionsField = $this->getOptionsField($table, (string) $context->filterModel?->fieldGeneric))
        {
            $preselect
                ->merge(['reference' => $optionsField['reference'] ?? []])
                ->options(fn (): array => $this->tryGetOptionsFromField($table, $optionsField) ?? []);
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            $preselect->options([]);
        }
    }

    public function getFieldGenericOptions(string $table): array
    {
        Controller::loadDataContainer($table);

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'])) {
            return [];
        }

        // find all fields with a type of select
        $options = [];
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] as $name => $field)
        {
            if ('select' === ($field['inputType'] ?? null)) {
                $options[$name] = $table . '.' . $name;
            }
        }

        return $options;
    }

    public function getOptions(string $table, ?string $field): ?array
    {
        $optionsField = $this->getOptionsField($table, $field) ?? [];
        $options = $this->tryGetOptionsFromField($table, $optionsField);

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

    public function getOptionsField(string $table, ?string $field): ?array
    {
        if (!$table || !$field) {
            return null;
        }

        Controller::loadLanguageFile($table);
        Controller::loadDataContainer($table);

        return $GLOBALS['TL_DCA'][$table]['fields'][$field] ?? null;
    }

    protected function tryGetOptionsFromField(string $table, array $optionsField): ?array
    {
        if (\is_array($options = $optionsField['options'] ?? null))
        {
            return $options;
        }

        if ($optionsCallback = $optionsField['options_callback'] ?? null)
        {
            $dataContainer = $this->mockDataContainerObject($table);

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
