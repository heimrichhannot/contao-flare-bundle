<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A single checkbox. Checked means true; unchecked means false only under the `true_false` binary
 * choices, otherwise the filter contributes nothing.
 */
#[AsFilterControl(name: self::NAME)]
class CheckboxFilterControl extends AbstractFilterControl
{
    public const NAME = 'flare_checkbox';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('binary_choices')
            ->default(BoolBinaryChoices::NULL_TRUE)
            ->allowedTypes(BoolBinaryChoices::class);
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
        $choices = BoolBinaryChoices::tryFrom((string) $model->ctl_boolBinaryChoices) ?? BoolBinaryChoices::NULL_TRUE;

        // NULL_FALSE is retired; it behaved like TRUE_FALSE for a checkbox.
        if ($choices === BoolBinaryChoices::NULL_FALSE) {
            $choices = BoolBinaryChoices::TRUE_FALSE;
        }

        $config
            ->set('label', $model->ctl_label ?: null)
            ->set('binary_choices', $choices);
    }

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
    {
        $dca->palette('{control_legend},ctl_label,ctl_boolBinaryChoices');
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $builder->single(CheckboxType::class, [
            'label' => $scope->filter->controlConfig['label'] ?: false,
            'required' => false,
        ]);
    }

    public function decode(mixed $data, FilterScope $scope): ?BoolValue
    {
        if ($data === true) {
            return new BoolValue(true);
        }

        $choices = $scope->filter->controlConfig['binary_choices'] ?? BoolBinaryChoices::NULL_TRUE;

        return $data === false && $choices === BoolBinaryChoices::TRUE_FALSE ? new BoolValue(false) : null;
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        return $this->expectValue($value, BoolValue::class)?->state;
    }
}
