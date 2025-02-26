<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Manager\FilterElementManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterFilterElementsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(FilterElementManager::class)) {
            return;
        }

        $manager = $container->findDefinition(FilterElementManager::class);
        $taggedDefinitions = $container->findTaggedServiceIds(FilterElementManager::TAG_FLARE_FILTER_ELEMENT);

        foreach ($taggedDefinitions as $serviceId => $definition)
        {
            if (!$container->has($serviceId)) {
                continue;
            }

            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if (!$class || !\class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->getAttributes(AsFilterElement::class)) {
                $manager->addMethodCall('registerFilterElement', [$class]);
            }
        }
    }
}