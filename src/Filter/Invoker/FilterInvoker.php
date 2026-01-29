<?php

namespace HeimrichHannot\FlareBundle\Filter\Invoker;

use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterInvokerRegistry;
use Psr\Container\ContainerInterface;

readonly class FilterInvoker
{
    public function __construct(
        private FilterInvokerRegistry $registry,
        private FilterElementRegistry $elementRegistry,
        private ContainerInterface    $invokerLocator,
    ) {}

    public function get(string $filterType, string $contextType): ?callable
    {
        // If a custom resolver is found, return the callable from the service locator
        if ($invokerConfig = $this->registry->find($filterType, $contextType))
        {
            $service = $this->invokerLocator->get($invokerConfig['serviceId']);
            return [$service, $invokerConfig['method']];
        }

        // Fallback to the element itself
        if ($elementDescriptor = $this->elementRegistry->get($filterType))
        {
            $method = $elementDescriptor->getMethod() ?? '__invoke';
            $service = $elementDescriptor->getService();

            if (\method_exists($service, $method)) {
                return [$service, $method];
            }
        }
        
        // No invoker found
        return null;
    }
}
