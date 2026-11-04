<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\Contao\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A free-text input for a search phrase.
 */
#[AsFilterControl(type: self::TYPE)]
class KeywordsFilterControl extends AbstractFilterControl
{
    public const TYPE = 'flare_keywords';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('placeholder')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('label', $model->ctl_label ?: null)
            ->set('placeholder', $model->ctl_placeholder ?: null);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{control_legend},ctl_label,ctl_placeholder');
    }

    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
    {
        $options = [
            'label' => $context->get('label') ?: false,
            'required' => false,
        ];

        if ($context->get('placeholder')) {
            $options['attr'] = ['placeholder' => $context->get('placeholder')];
        }

        $builder->single(TextType::class, $options);
    }

    /**
     * @param string|null $data The submitted search phrase.
     */
    public function decode(mixed $data, ControlContext $context): ?KeywordsValue
    {
        return KeywordsValue::tryFrom($data);
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): ?string
    {
        return $this->expectValue($value, KeywordsValue::class)?->keywords;
    }
}
