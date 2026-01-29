<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use HeimrichHannot\FlareBundle\Filter\Invoker\FilterInvoker;
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

        foreach ($container->getDefinitions() as $serviceId => $definition)
        {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            $class = $definition->getClass();
            if (\is_null($class) || !\class_exists($class)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($class);

            // Process class attributes
            $attributes = $reflectionClass->getAttributes(AsFilterInvoker::class);
            if (\count($attributes) > 0)
            {
                foreach ($attributes as $attribute)
                {
                    $instance = $attribute->newInstance();
                    if (!$instance instanceof AsFilterInvoker) {
                        throw new \LogicException(sprintf('The #[AsFilterInvoker] attribute on service "%s" must be an instance of "%s".', $serviceId, AsFilterInvoker::class));
                    }
                    $this->processAttribute(
                        attribute: $instance,
                        serviceId: $serviceId,
                        definition: $definition,
                        class: $reflectionClass,
                        methodName: null,
                        registryDefinition: $registryDefinition
                    );
                    $invokerServiceIds[$serviceId] = new Reference($serviceId);
                }
            }

            // Process method attributes
            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method)
            {
                $attributes = $method->getAttributes(AsFilterInvoker::class);
                if (\count($attributes) > 0)
                {
                    foreach ($attributes as $attribute)
                    {
                        $instance = $attribute->newInstance();
                        if (!$instance instanceof AsFilterInvoker) {
                            throw new \LogicException(sprintf('The #[AsFilterInvoker] attribute on service "%s" must be an instance of "%s".', $serviceId, AsFilterInvoker::class));
                        }
                        $this->processAttribute(
                            attribute: $instance,
                            serviceId: $serviceId,
                            definition: $definition,
                            class: $reflectionClass,
                            methodName: $method->getName(),
                            registryDefinition: $registryDefinition
                        );
                        $invokerServiceIds[$serviceId] = new Reference($serviceId);
                    }
                }
            }
        }
        
        if ($container->hasDefinition(FilterInvoker::class))
        {
            $invokerDefinition = $container->getDefinition(FilterInvoker::class);
            $invokerDefinition->setArgument(
                '$invokerLocator',
                (new Definition(ServiceLocator::class, [$invokerServiceIds]))
                    ->addTag('container.service_locator')
            );
        }
    }

    private function processAttribute(
        AsFilterInvoker  $attribute,
        string           $serviceId,
        Definition       $definition,
        \ReflectionClass $class,
        ?string          $methodName,
        Definition       $registryDefinition
    ): void {
        $method = $methodName ?? $attribute->method;

        if ($methodName !== null && $attribute->method !== null) {
            throw new InvalidArgumentException(sprintf('You cannot specify the "method" property on the #[AsFilterInvoker] attribute when it decorates a method in service "%s".', $serviceId));
        }

        if ($method === null) {
            $method = '__invoke';
        }

        if (!$class->hasMethod($method) || !$class->getMethod($method)->isPublic()) {
            throw new InvalidArgumentException(sprintf('The invoker method "%s" does not exist or is not public on service "%s".', $method, $serviceId));
        }

        $filterType = $attribute->filterType;
        $elementAttributes = $class->getAttributes(AsFilterElement::class);
        $isFilterElement = \count($elementAttributes) > 0;

        if (null !== $filterType)
        {
            if (!$filterType) {
                throw new InvalidArgumentException(sprintf('The "filterType" property on the #[AsFilterInvoker] attribute on service "%s" MUST be specified and cannot be empty.', $serviceId));
            }

            $registryDefinition->addMethodCall('add', [
                $filterType,
                $attribute->context,
                $serviceId,
                $method,
                $attribute->priority,
            ]);

            return;
        }

        if (!$isFilterElement) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not a filter element, thus the "filterType" property on the #[AsFilterInvoker] attribute MUST be specified.', $serviceId));
        }

        foreach ($elementAttributes as $elementAttribute)
        {
            /** @var AsFilterElement $instance */
            $instance = $elementAttribute->newInstance();

            if (!$instance instanceof AsFilterElement) {
                throw new \LogicException(sprintf('The #[AsFilterElement] attribute on service "%s" must be an instance of "%s".', $serviceId, AsFilterElement::class));
            }

            if (!$type = (string) ($instance->attributes['type'] ?? null)) {
                $type = TypeNameFactory::createFilterElementType($definition->getClass());
            }

            $registryDefinition->addMethodCall('add', [
                $type,
                $attribute->context,
                $serviceId,
                $method,
                $attribute->priority,
            ]);
        }
    }
}