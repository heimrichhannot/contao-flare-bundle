<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;
use HeimrichHannot\FlareBundle\Manager\FlareFilterElementManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterFilterElementPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(FlareFilterElementManager::class)) {
            return;
        }

        $manager = $container->findDefinition(FlareFilterElementManager::class);
        $taggedDefinitions = $container->findTaggedServiceIds(FlareFilterElementManager::TAG_FLARE_FILTER_ELEMENT);

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

            if ($reflection->getAttributes(AsFlareFilterElement::class)) {
                $manager->addMethodCall('registerFilterElement', [$class]);
            }
        }
    }
}