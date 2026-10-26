<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A yes/no/any select for a boolean value.
 */
#[AsFilterControl(name: self::NAME)]
class ChoiceBoolFilterControl extends AbstractFilterControl
{
    public const NAME = 'flare_choice_bool';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(
            sourceClass: FilterModel::class,
            transformer: new CallbackFilterModelTransformer(
                static fn (ConfigBuilder $config, FilterModel $model): ConfigBuilder
                    => $config->set('label', $model->ctl_label ?: null),
            ),
        );
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{control_legend},ctl_label');
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $builder->single(ChoiceType::class, [
            'label' => $scope->filter->controlConfig['label'] ?: false,
            'required' => false,
            'choices' => [
                'empty_option.yes' => '1',
                'empty_option.no' => '0',
            ],
            'placeholder' => 'empty_option.all',
            'translation_domain' => 'flare_form',
        ]);
    }

    public function decode(mixed $data, FilterScope $scope): ?BoolValue
    {
        return match ($data) {
            '1' => new BoolValue(true),
            '0' => new BoolValue(false),
            default => null,
        };
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        $value = $this->expectValue($value, BoolValue::class);

        return match ($value?->state) {
            true => '1',
            false => '0',
            null => null,
        };
    }
}
