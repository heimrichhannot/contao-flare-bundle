<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Collects `flare.filter_control` tags into {@see FilterControlRegistry}. Besides the pattern of
 * {@see RegisterFilterElementsPass} it checks only the value class (the narrowed `decode()` return
 * type) and type uniqueness.
 */
final class RegisterFilterControlsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(FilterControlRegistry::class)) {
            return;
        }

        $tag = AsFilterControl::TAG;
        $registry = $container->findDefinition(FilterControlRegistry::class);

        /** @var array<string, string> $services Service id by registered type. */
        $services = [];

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            $serviceId = (string) $reference;
            $definition = $container->findDefinition($serviceId);
            $tags = $definition->getTag($tag);
            $definition->clearTag($tag);

            $class = $this->assertIsFilterControl($definition, $serviceId);
            $valueClass = ValueClassReflector::fromReturnType($class, 'decode');

            foreach ($tags as $attributes)
            {
                $type = $this->getFilterControlType($definition, $attributes);

                if (isset($services[$type]))
                {
                    throw new \InvalidArgumentException(\sprintf(
                        'The filter control type "%s" is already registered by service "%s"; service "%s" must'
                        . ' declare a different type. Control types are persisted in "%s.control" and must be unique.',
                        $type,
                        $services[$type],
                        $serviceId,
                        FilterContainer::TABLE_NAME,
                    ));
                }

                $services[$type] = $serviceId;

                /** @see FilterControlRegistry::add() */
                $registry->addMethodCall('add', [$reference, $type, $valueClass]);

                $container
                    ->setAlias('flare.filter_control.' . $type, $serviceId)
                    ->setPublic(true);
            }
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function getFilterControlType(Definition $definition, array $attributes): string
    {
        if ($type = (string) ($attributes['type'] ?? ''))
        {
            if ($type === 'default') {
                throw new \InvalidArgumentException(
                    'The filter control type "default" is reserved and cannot be used. Choose a different type.',
                );
            }

            return $type;
        }

        $class = $definition->getClass();

        if (!$class) {
            throw new \InvalidArgumentException(
                'A filter control service without a class must declare an explicit type.',
            );
        }

        $type = TypeNameFactory::createFilterControlType($class);

        // A class named exactly "FilterControl" reduces to "", the intrinsic sentinel in tl_flare_filter.control.
        if ($type === '' || $type === 'default')
        {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot derive a filter control type from class "%s" (derived "%s"). Declare an explicit type.',
                $class,
                $type,
            ));
        }

        return $type;
    }

    /**
     * @return class-string
     */
    private function assertIsFilterControl(Definition $definition, string $serviceId): string
    {
        $class = $definition->getClass();

        if ($class === null || !\class_exists($class))
        {
            throw new \InvalidArgumentException(\sprintf(
                'Service "%s" is tagged "%s" but its class "%s" cannot be loaded.',
                $serviceId,
                AsFilterControl::TAG,
                (string) $class,
            ));
        }

        if (!\is_a($class, FilterControlInterface::class, true))
        {
            throw new \InvalidArgumentException(\sprintf(
                'Service "%s" is tagged "%s" but "%s" does not implement %s.',
                $serviceId,
                AsFilterControl::TAG,
                $class,
                FilterControlInterface::class,
            ));
        }

        return $class;
    }
}
