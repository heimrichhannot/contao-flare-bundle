<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection;

use Exception;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementConfig;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class HeimrichHannotFlareExtension extends Extension
{
    public const ALIAS = 'huh_flare';

    public function getAlias(): string
    {
        return static::ALIAS;
    }

    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('services.yaml');

        $container->registerAttributeForAutoconfiguration(
            AsFilterElement::class,
            static function (ChildDefinition  $definition, AsFilterElement  $attribute): void {
                $definition->addTag(FilterElementConfig::TAG, $attribute->attributes);
            }
        );
    }
}