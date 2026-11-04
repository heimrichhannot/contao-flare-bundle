<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FactoryStubBoolControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default('default')->allowedTypes('string');
    }

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(FilterModel::class, static function (ConfigBuilder $config, FilterModel $model): void {
            $config->set('label', 'from model');
        });
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void {}

    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}
