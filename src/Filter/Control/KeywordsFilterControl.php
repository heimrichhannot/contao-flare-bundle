<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
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

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $config = $scope->filter->controlConfig;

        $options = [
            'label' => $config['label'] ?: false,
            'required' => false,
        ];

        if ($config['placeholder']) {
            $options['attr'] = ['placeholder' => $config['placeholder']];
        }

        $builder->single(TextType::class, $options);
    }

    /**
     * @param string|null $data The submitted search phrase.
     */
    public function decode(mixed $data, FilterScope $scope): ?KeywordsValue
    {
        return KeywordsValue::tryFrom($data);
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): ?string
    {
        return $this->expectValue($value, KeywordsValue::class)?->keywords;
    }
}
