<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CodefogTagsManagerFinderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $serviceIds = \array_filter(
            \array_keys($container->getDefinitions()),
            static fn (string $id): bool => \str_starts_with($id, 'codefog_tags.manager.')
        );

        $targets = [];

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

            $targets[$table] ??= [];
            $targets[$table][] = $field;
        }

        $container->setParameter('flare_codefog_tags_targets', $targets);
    }
}