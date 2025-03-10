<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFlareCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Filter\FilterElementConfig;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackConfig;
use HeimrichHannot\FlareBundle\List\ListTypeConfig;
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
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('services.yaml');

        $attributesForAutoconfiguration = [
            AsFlareCallback::class => FlareCallbackConfig::TAG,
            AsFilterCallback::class => FlareCallbackConfig::TAG_FILTER_CALLBACK,
            AsFilterElement::class => FilterElementConfig::TAG,
            AsListType::class => ListTypeConfig::TAG,
            AsListCallback::class => FlareCallbackConfig::TAG_LIST_CALLBACK,
        ];

        foreach ($attributesForAutoconfiguration as $attributeClass => $tag)
        {
            $container->registerAttributeForAutoconfiguration(
                $attributeClass,
                static function (ChildDefinition $definition, object $attribute, \Reflector $reflector) use ($attributeClass, $tag): void {
                    $tagAttributes = \property_exists($attribute, 'attributes')
                        ? $attribute->attributes
                        : \get_object_vars($attribute);

                    if ($reflector instanceof \ReflectionMethod)
                    {
                        if (isset($tagAttributes['method'])) {
                            throw new \LogicException(
                                sprintf(
                                    '%s attribute cannot declare a method on "%s::%s()".',
                                    $attributeClass,
                                    $reflector->getDeclaringClass()->getName(),
                                    $reflector->getName()
                                )
                            );
                        }

                        $tagAttributes['method'] = $reflector->getName();
                    }

                    $definition->addTag($tag, $tagAttributes);
                }
            );
        }
    }
}