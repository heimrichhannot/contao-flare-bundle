<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsManagersRegistry;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry\CfgTagsManagersResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class CodefogTagsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CfgTagsManagersRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(CfgTagsManagersRegistry::class);

        $serviceIds = \array_filter(
            \array_keys($container->getDefinitions()),
            static fn (string $id): bool => \str_starts_with($id, 'codefog_tags.manager.')
        );

        $managerLocations = [];

        foreach ($serviceIds as $id)
        {
            $serviceDefinition = $container->getDefinition($id);

            if (!$source = $serviceDefinition->getArguments()[1][0] ?? null) {
                continue;
            }

            if (!\str_contains($source, '.')) {
                continue;
            }

            [$table, $field] = \explode('.', $source, 2);

            $registry->addMethodCall('set', [$table, $field, $id]);
            $managerLocations[$id] = new Reference($id);
        }

        if ($container->hasDefinition(CfgTagsManagersResolver::class))
        {
            $resolverDefinition = $container->getDefinition(CfgTagsManagersResolver::class);
            $resolverDefinition->setArgument(
                '$managerLocator',
                (new Definition(ServiceLocator::class, [$managerLocations]))
                    ->addTag('container.service_locator')
            );
        }
    }
}