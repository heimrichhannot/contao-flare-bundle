<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Controller;
use Contao\DataContainer;
use Contao\System;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Predicate\DcaSelectPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, value: ChoiceValue::class)]
class DcaSelectFieldFilterElement extends AbstractFilterElement implements ChoiceSourceContract
{
    public const TYPE = 'flare_dcaSelectField';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('field', $model->elm_fieldGeneric ?: null);
    }

    /**
     * @throws FilterException
     */
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        if (!$value = $this->expectValue($value, ChoiceValue::class)) {
            return;
        }

        if (!$field = $builder->scope->filter->config['field'] ?? null) {
            $builder->abort();
        }

        $table = $builder->scope->list->dc;

        if (!$options = $this->getOptions($table, $field)) {
            $builder->abort();
        }

        $selected = [];

        foreach ($value->choices as $choice)
        {
            if ($choice instanceof ValueChoice) {
                $selected[] = $choice->value;
            }
        }

        if (!$selected) {
            $builder->abort();
        }

        $isMultiple = $this->getOptionsField($table, $field)['eval']['multiple'] ?? false;

        $builder->addPredicate(DcaSelectPredicate::class, [
            'field' => $field,
            'selected' => $selected,
            'valid_options' => $options,
            'is_multiple_dca_field' => (bool) $isMultiple,
        ]);
    }

    /**
     * @throws FilterException
     */
    public function createChoices(FilterScope $scope): ChoicesBuilder
    {
        $field = $scope->filter->config['field'] ?? null;

        if (\is_null($options = $this->getOptions($scope->list->dc, $field))) {
            throw new FilterException(
                \sprintf('No options found for the select field "%s.%s".', $scope->list->dc, (string) $field),
                method: __METHOD__,
            );
        }

        $choices = $this->createChoicesBuilder();

        foreach ($options as $key => $label)
        {
            // A blank option (e.g. a DCA's "-" entry) would collide with the control's empty option (§7.3).
            if (($key = (string) $key) === '') {
                continue;
            }

            $choices->add(new ValueChoice($key, (string) $label));
        }

        return $choices;
    }

    public function choiceFromKey(string $key): ValueChoice
    {
        return new ValueChoice($key);
    }

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},elm_fieldGeneric');

        $dca->field('elm_fieldGeneric')
            ->eval(['alwaysSave' => true, 'submitOnChange' => true])
            ->options(fn (): array => $this->getFieldGenericOptions($context->listModel->dc));
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
