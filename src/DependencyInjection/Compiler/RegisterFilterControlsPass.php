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
 * type) and name uniqueness.
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

        /** @var array<string, string> $services Service id by registered name. */
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
                $name = $this->getFilterControlName($definition, $attributes);

                if (isset($services[$name]))
                {
                    throw new \InvalidArgumentException(\sprintf(
                        'The filter control name "%s" is already registered by service "%s"; service "%s" must'
                        . ' declare a different name. Control names are persisted in "%s.control" and must be unique.',
                        $name,
                        $services[$name],
                        $serviceId,
                        FilterContainer::TABLE_NAME,
                    ));
                }

                $services[$name] = $serviceId;

                /** @see FilterControlRegistry::add() */
                $registry->addMethodCall('add', [$reference, $name, $valueClass]);

                $container
                    ->setAlias('flare.filter_control.' . $name, $serviceId)
                    ->setPublic(true);
            }
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function getFilterControlName(Definition $definition, array $attributes): string
    {
        if ($name = (string) ($attributes['name'] ?? ''))
        {
            if ($name === 'default') {
                throw new \InvalidArgumentException(
                    'The filter control name "default" is reserved and cannot be used. Choose a different name.',
                );
            }

            return $name;
        }

        $class = $definition->getClass();

        if (!$class) {
            throw new \InvalidArgumentException(
                'A filter control service without a class must declare an explicit name.',
            );
        }

        $name = TypeNameFactory::createFilterControlType($class);

        // A class named exactly "FilterControl" reduces to "", the intrinsic sentinel in tl_flare_filter.control.
        if ($name === '' || $name === 'default')
        {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot derive a filter control name from class "%s" (derived "%s"). Declare an explicit name.',
                $class,
                $name,
            ));
        }

        return $name;
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
