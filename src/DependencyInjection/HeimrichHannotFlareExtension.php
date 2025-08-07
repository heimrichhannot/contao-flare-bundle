<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFlareCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FlareCallbackDescriptor;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class HeimrichHannotFlareExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $flareConfig = $this->processConfiguration($configuration, $configs);

        $container->setParameter($this->getAlias(), $flareConfig);
        $container->setParameter($this->getAlias() . '.format_label_defaults', $flareConfig['format_label_defaults'] ?? []);

        $attributesForAutoconfiguration = [
            AsListType::class => ListTypeDescriptor::TAG,
            AsFilterElement::class => FilterElementDescriptor::TAG,
            AsFlareCallback::class => FlareCallbackDescriptor::TAG,
            AsFilterCallback::class => FlareCallbackDescriptor::TAG_FILTER_CALLBACK,
            AsListCallback::class => FlareCallbackDescriptor::TAG_LIST_CALLBACK,
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

    public function getAlias(): string
    {
        return 'huh_flare';
    }

    public function prepend(ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('config.yaml');
    }
}