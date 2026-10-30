<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class RegisterFilterElementsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(FilterElementRegistry::class)) {
            return;
        }

        $tag = AsFilterElement::TAG;
        $registry = $container->findDefinition(FilterElementRegistry::class);
        $providedValueClasses = $this->getProvidedValueClasses($container);

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            $definition = $container->findDefinition((string) $reference);
            $tags = $definition->getTag($tag);
            $definition->clearTag($tag);

            foreach ($tags as $attributes)
            {
                $type = $this->getFilterElementType($definition, $attributes);

                $valueClass = $attributes['value'] ?? null;

                if ($valueClass !== null && !isset($providedValueClasses[$valueClass]))
                {
                    throw new \InvalidArgumentException(\sprintf(
                        'The filter element "%s" declares the value class "%s", which has no value provider.'
                        . ' Register a service implementing %s whose provide() returns it.',
                        $type,
                        $valueClass,
                        ValueProviderInterface::class,
                    ));
                }

                /** @see AsFilterElement::__construct */
                $attribute = new Definition(AsFilterElement::class, [
                    $type,
                    $attributes['isTargeted'] ?? null,
                    $attributes['value'] ?? null,
                ]);

                /** @see FilterElementRegistry::add() */
                $registry->addMethodCall('add', [$reference, $attribute, $type]);

                $serviceId = 'flare.filter_element.' . $type;

                $container
                    ->setAlias($serviceId, (string) $reference)
                    ->setPublic(true);
            }
        }
    }

    /**
     * @return array<string, true> The value classes registered with the value provider registry so far.
     */
    private function getProvidedValueClasses(ContainerBuilder $container): array
    {
        if (!$container->has(ValueProviderRegistry::class)) {
            return [];
        }

        $classes = [];

        foreach ($container->findDefinition(ValueProviderRegistry::class)->getMethodCalls() as [$method, $arguments])
        {
            if ($method === 'add' && isset($arguments[1])) {
                $classes[(string) $arguments[1]] = true;
            }
        }

        return $classes;
    }

    protected function getFilterElementType(Definition $definition, array $attributes): string
    {
        if ($type = (string) ($attributes['type'] ?? null))
        {
            if ($type === 'default') {
                throw new \InvalidArgumentException('The filter element type "default" is reserved and cannot be used. Choose a different type name.');
            }

            return $type;
        }

        return TypeNameFactory::createFilterElementType($definition->getClass());
    }
}
