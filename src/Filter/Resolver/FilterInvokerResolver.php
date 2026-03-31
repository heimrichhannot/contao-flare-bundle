<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Filter\FilterInvokerInterface;
use HeimrichHannot\FlareBundle\Filter\ServiceMethodFilterInvoker;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterInvokerRegistry;
use Psr\Container\ContainerInterface;

final readonly class FilterInvokerResolver
{
    public function __construct(
        private FilterInvokerRegistry $registry,
        private FilterElementRegistry $elementRegistry,
        private ContainerInterface    $invokerLocator,
    ) {}

    public function get(string $filterType, string $contextType): ?FilterInvokerInterface
    {
        // If a custom resolver is found, return the callable from the service locator
        if ($invokerConfig = $this->registry->find($filterType, $contextType))
        {
            $service = $this->invokerLocator->get($invokerConfig['serviceId']);
            return $this->resolveCallback($service, $invokerConfig['method']);
        }

        // Fallback to the element itself
        if ($elementDescriptor = $this->elementRegistry->get($filterType))
        {
            $method = $elementDescriptor->getMethod() ?? '__invoke';
            $service = $elementDescriptor->getService();

            if (\method_exists($service, $method)) {
                return new ServiceMethodFilterInvoker($service, $method);
            }
        }
        
        // No invoker found
        return null;
    }

    private function resolveCallback(object $service, string $method): ?FilterInvokerInterface
    {
        if (!\method_exists($service, $method)) {
            return null;
        }

        if ($method === '__invoke' && $service instanceof FilterInvokerInterface) {
            return $service;
        }

        return new ServiceMethodFilterInvoker($service, $method);
    }
}