<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A yes/no/any select for a boolean value.
 */
#[AsFilterControl(type: self::TYPE)]
class ChoiceBoolFilterControl extends AbstractFilterControl
{
    public const TYPE = 'flare_choice_bool';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('label', $model->ctl_label ?: null);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{control_legend},ctl_label');
    }

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
        $builder->single(ChoiceType::class, [
            'label' => $scope->filter->control->config->all()['label'] ?: false,
            'required' => false,
            'choices' => [
                'empty_option.yes' => '1',
                'empty_option.no' => '0',
            ],
            'placeholder' => 'empty_option.all',
            'translation_domain' => 'flare_form',
        ]);
    }

    /**
     * @param string|null $data The selected choice value: "1", "0", or null for the empty option.
     */
    public function decode(ControlScope $scope, mixed $data): ?BoolValue
    {
        return match ($data) {
            '1' => new BoolValue(true),
            '0' => new BoolValue(false),
            default => null,
        };
    }

    public function hydrate(ControlScope $scope, ?ValueInterface $value): ?string
    {
        $value = $this->expectValue($value, BoolValue::class);

        return match ($value?->state) {
            true => '1',
            false => '0',
            null => null,
        };
    }
}
