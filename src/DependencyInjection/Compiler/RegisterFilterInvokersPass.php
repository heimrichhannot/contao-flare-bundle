<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker;
use HeimrichHannot\FlareBundle\Filter\Invoker\FilterInvoker;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
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
                    $this->processAttribute($instance, $serviceId, $definition, $reflectionClass, null, $registryDefinition);
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
                        $this->processAttribute($instance, $serviceId, $definition, $reflectionClass, $method->getName(), $registryDefinition);
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
        $filterType = $attribute->filterType;
        $isFilterElement = $class->isSubclassOf(AbstractFilterElement::class);

        // Inference Logic
        if ($filterType === null) {
            if (!$isFilterElement) {
                throw new InvalidArgumentException(sprintf('Service "%s" is not a filter element, so you must specify the "filterType" property on the #[AsFilterInvoker] attribute.', $serviceId));
            }
            $filterType = $class->getMethod('getType')->invoke(null);
        } elseif ($isFilterElement) {
            throw new InvalidArgumentException(sprintf('Service "%s" is a filter element, so you must not specify the "filterType" property on the #[AsFilterInvoker] attribute; it is automatically inferred.', $serviceId));
        }
        
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
        
        $registryDefinition->addMethodCall('add', [
            $filterType,
            $attribute->context,
            $serviceId,
            $method,
            $attribute->priority,
        ]);
    }
}