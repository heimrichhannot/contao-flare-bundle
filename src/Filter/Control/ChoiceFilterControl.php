<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Presents the options of a {@see ChoiceSourceContract} element as a select, radio or checkbox group.
 */
#[AsFilterControl(type: self::TYPE)]
class ChoiceFilterControl extends AbstractFilterControl
{
    public const TYPE = 'flare_choice';

    public function __construct(
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

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

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $formatLabel = $model->ctl_formatLabel === 'custom' ? $model->ctl_formatLabelCustom : $model->ctl_formatLabel;
        $formatEmptyOption = $model->ctl_formatEmptyOption === 'custom'
            ? $model->ctl_formatEmptyOptionCustom
            : $model->ctl_formatEmptyOption;

        $config
            ->set('label', $model->ctl_label ?: null)
            ->set('format_label', $formatLabel ?: null)
            ->set('is_mandatory', (bool) $model->ctl_isMandatory)
            ->set('is_multiple', (bool) $model->ctl_isMultiple)
            ->set('is_expanded', (bool) $model->ctl_isExpanded)
            ->set('has_empty_option', (bool) $model->ctl_hasEmptyOption)
            ->set('format_empty_option', $formatEmptyOption ?: null)
            ->set('placeholder', $model->ctl_placeholder ?: null);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette(
            '{control_legend},ctl_label,ctl_formatLabel,ctl_isMandatory,ctl_isMultiple,ctl_isExpanded,'
            . 'ctl_hasEmptyOption,ctl_formatEmptyOption,ctl_placeholder',
        );
    }

    /**
     * @throws FilterException
     */
    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $element = $scope->filter->element->component;

        if (!$element instanceof ChoiceSourceContract) {
            throw new FilterException(\sprintf(
                'The control "%s" requires an element implementing %s, got %s.',
                self::TYPE,
                ChoiceSourceContract::class,
                $element::class,
            ));
        }

        $config = $scope->filter->control->config->all();
        $choices = $this->choicesBuilderFactory->createChoicesBuilder();
        $element->buildChoices($choices, $scope);

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
            // The empty option is a real choice (§7.3); Symfony's implicit placeholder would add a second,
            // blank option or an untranslated "None" radio.
            'placeholder' => false,
        ];

        if ($config['placeholder'] && !$choices->hasEmptyOption()) {
            $options['attr'] = ['placeholder' => $config['placeholder']];
        }

        $choices->applyFormOptions($options);

        $builder->single(ChoiceType::class, $options);
    }

    /**
     * @param ChoiceObjectInterface|iterable<mixed>|null $data The selected choice, or the selected choices of a
     *   multiple widget; entries that are no choice objects (e.g. the empty option) are ignored.
     */
    public function decode(mixed $data, FilterScope $scope): ?ChoiceValue
    {
        return ChoiceValue::tryFrom($data);
    }

    /**
     * @return ChoiceObjectInterface|list<ChoiceObjectInterface>|null A list for a multiple widget.
     */
    public function hydrate(?ValueInterface $value, FilterScope $scope): ChoiceObjectInterface|array|null
    {
        $value = $this->expectValue($value, ChoiceValue::class);

        if ($scope->filter->control->config->all()['is_multiple'] ?? false) {
            return $value->choices ?? [];
        }

        return $value->choices[0] ?? null;
    }
}
