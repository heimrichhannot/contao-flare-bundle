<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Presents the options of a {@see ChoiceSourceContract} element as a select, radio or checkbox group.
 */
#[AsFilterControl(name: self::NAME)]
class ChoiceFilterControl extends AbstractFilterControl
{
    public const NAME = 'flare_choice';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('format_label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('is_mandatory')->default(false)->allowedTypes('bool');
        $resolver->define('is_multiple')->default(false)->allowedTypes('bool');
        $resolver->define('is_expanded')->default(false)->allowedTypes('bool');
        $resolver->define('has_empty_option')->default(false)->allowedTypes('bool');
        $resolver->define('format_empty_option')->default(null)->allowedTypes('string', 'null');
        $resolver->define('placeholder')->default(null)->allowedTypes('string', 'null');
    }

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(
            sourceClass: FilterModel::class,
            transformer: new CallbackFilterModelTransformer($this->transformFilterModel(...)),
        );
    }

    private function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $formatLabel = $model->formatLabel === 'custom' ? $model->formatLabelCustom : $model->formatLabel;
        $formatEmptyOption = $model->formatEmptyOption === 'custom'
            ? $model->formatEmptyOptionCustom
            : $model->formatEmptyOption;

        $config
            ->set('label', $model->label ?: null)
            ->set('format_label', $formatLabel ?: null)
            ->set('is_mandatory', (bool) $model->isMandatory)
            ->set('is_multiple', (bool) $model->isMultiple)
            ->set('is_expanded', (bool) $model->isExpanded)
            ->set('has_empty_option', (bool) $model->hasEmptyOption)
            ->set('format_empty_option', $formatEmptyOption ?: null)
            ->set('placeholder', $model->placeholder ?: null);
    }

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
    {
        $dca->palette(
            '{control_legend},label,formatLabel,isMandatory,isMultiple,isExpanded,hasEmptyOption,placeholder',
        );
    }

    /**
     * @throws FilterException
     */
    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $element = $scope->filter->element;

        if (!$element instanceof ChoiceSourceContract) {
            throw new FilterException(\sprintf(
                'The control "%s" requires an element implementing %s, got %s.',
                self::NAME,
                ChoiceSourceContract::class,
                $element::class,
            ));
        }

        $config = $scope->filter->controlConfig;
        $choices = $element->createChoices($scope);

        if ($config['format_label']) {
            $choices->setLabel($config['format_label']);
        }

        if ($config['has_empty_option'])
        {
            $emptyOptionValue = ($config['is_expanded'] && $config['is_multiple'])
                ? ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE
                : null;

            $choices->setEmptyOption($config['format_empty_option'] ?: true, $emptyOptionValue);
        }

        $options = [
            'label' => $config['label'] ?: false,
            'required' => $config['is_mandatory'],
            'multiple' => $config['is_multiple'],
            'expanded' => $config['is_expanded'],
        ];

        if ($config['placeholder'] && !$choices->hasEmptyOption()) {
            $options['attr'] = ['placeholder' => $config['placeholder']];
        }

        $choices->applyFormOptions($options);

        $builder->single(ChoiceType::class, $options);
    }

    public function decode(mixed $data, FilterScope $scope): ?ChoiceValue
    {
        return ChoiceValue::tryFrom($data);
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        $value = $this->expectValue($value, ChoiceValue::class);

        if ($scope->filter->controlConfig['is_multiple'] ?? false) {
            return $value?->choices ?? [];
        }

        return $value?->choices[0] ?? null;
    }
}
