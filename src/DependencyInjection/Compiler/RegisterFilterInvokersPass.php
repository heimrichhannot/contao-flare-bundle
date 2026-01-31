<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use HeimrichHannot\FlareBundle\FilterInvoker\FilterInvokerResolver;
use HeimrichHannot\FlareBundle\Registry\FilterInvokerRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RegisterFilterInvokersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FilterInvokerRegistry::class)) {
            return;
        }

        $registryDefinition = $container->getDefinition(FilterInvokerRegistry::class);
        $invokerServiceIds = [];

        $taggedServices = $container->findTaggedServiceIds(AsFilterInvoker::TAG);

        foreach ($taggedServices as $serviceId => $tags)
        {
            $definition = $container->getDefinition($serviceId);

            if ($definition->isAbstract()) {
                continue;
            }

            foreach ($tags as $attributes)
            {
                $this->processAttribute(
                    attributes: $attributes,
                    serviceId: $serviceId,
                    definition: $definition,
                    registryDefinition: $registryDefinition
                );
                $invokerServiceIds[$serviceId] = new Reference($serviceId);
            }
        }
        
        if ($container->hasDefinition(FilterInvokerResolver::class))
        {
            $invokerDefinition = $container->getDefinition(FilterInvokerResolver::class);
            $invokerDefinition->setArgument(
                '$invokerLocator',
                (new Definition(ServiceLocator::class, [$invokerServiceIds]))
                    ->addTag('container.service_locator')
            );
        }
    }

    private function processAttribute(
        array            $attributes,
        string           $serviceId,
        Definition       $definition,
        Definition       $registryDefinition
    ): void {
        $method = $attributes['method'] ?? '__invoke';
        $filterType = $attributes['filterType'] ?? null;
        $context = $attributes['context'] ?? null;
        $priority = $attributes['priority'] ?? 0;

        if (null !== $filterType)
        {
            if (!$filterType) {
                throw new InvalidArgumentException(sprintf('The "filterType" property on the #[AsFilterInvoker] attribute on service "%s" MUST NOT be empty.', $serviceId));
            }

            $registryDefinition->addMethodCall('add', [
                $filterType,
                $context,
                $serviceId,
                $method,
                $priority,
            ]);

            return;
        }

        // If filterType is null, we check if the service is a filter element
        $elementTags = $definition->getTag(AsFilterElement::TAG);
        $isFilterElement = \count($elementTags) > 0;

        if (!$isFilterElement) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not a filter element, thus the "filterType" property on the #[AsFilterInvoker] attribute MUST be specified.', $serviceId));
        }

        foreach ($elementTags as $elementAttributes)
        {
            if (!$type = (string) ($elementAttributes['type'] ?? null)) {
                $type = TypeNameFactory::createFilterElementType($definition->getClass());
            }

            $registryDefinition->addMethodCall('add', [
                $type,
                $context,
                $serviceId,
                $method,
                $priority,
            ]);
        }
    }
}
