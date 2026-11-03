<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects `flare.value_provider` tags into {@see ValueProviderRegistry}, keyed by the narrowed
 * `provide()` return type. Must run before {@see RegisterFilterElementsPass}, which verifies that
 * every element's `value:` class has a provider.
 */
final class RegisterValueProvidersPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ValueProviderRegistry::class)) {
            return;
        }

        $tag = ValueProviderInterface::TAG;
        $registry = $container->findDefinition(ValueProviderRegistry::class);

        /** @var array<string, string> $services Service id by value class. */
        $services = [];

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            $serviceId = (string) $reference;
            $definition = $container->findDefinition($serviceId);
            $definition->clearTag($tag);

            $class = $definition->getClass();

            if ($class === null || !\class_exists($class) || !\is_a($class, ValueProviderInterface::class, true))
            {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" is tagged "%s" but its class "%s" does not implement %s.',
                    $serviceId,
                    $tag,
                    (string) $class,
                    ValueProviderInterface::class,
                ));
            }

            $valueClass = ValueClassReflector::fromReturnType($class, 'provide');

            if (isset($services[$valueClass]))
            {
                throw new \InvalidArgumentException(\sprintf(
                    'The value class "%s" already has the value provider service "%s"; service "%s" cannot'
                    . ' provide it too. There is exactly one provider per value class.',
                    $valueClass,
                    $services[$valueClass],
                    $serviceId,
                ));
            }

            $services[$valueClass] = $serviceId;

            /** @see ValueProviderRegistry::add() */
            $registry->addMethodCall('add', [$reference, $valueClass]);
        }
    }
}
