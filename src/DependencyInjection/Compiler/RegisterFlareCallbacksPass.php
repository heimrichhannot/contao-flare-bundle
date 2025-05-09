<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackConfig;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterFlareCallbacksPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(FlareCallbackRegistry::class)) {
            return;
        }

        $mapTagPrefix = [
            FlareCallbackConfig::TAG => null,
            FlareCallbackConfig::TAG_FILTER_CALLBACK => 'filter',
            FlareCallbackConfig::TAG_LIST_CALLBACK => 'list',
        ];

        $registry = $container->findDefinition(FlareCallbackRegistry::class);

        foreach ($mapTagPrefix as $tag => $prefix)
        {
            foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
            {
                if (\str_starts_with((string) $reference, 'huh.flare.flare_callback._')) {
                    continue;
                }

                $definition = $container->findDefinition((string) $reference);
                $mapTagPrefix = $definition->getTag($tag);
                $definition->clearTag($tag);

                foreach ($mapTagPrefix as $attributes)
                {
                    $namespace = $prefix ? $prefix . '.' : '';
                    $namespace .= $attributes['element'] ?? null;
                    $target = $attributes['target'] ?? null;

                    if (!$namespace || !$target) {
                        continue;
                    }

                    $config = $this->getFilterCallbackConfig($container, $reference, $attributes);

                    /** @see FlareCallbackRegistry::add() */
                    $registry->addMethodCall('add', [$namespace, $target, (int) ($attributes['priority'] ?? 0), $config]);
                }
            }
        }
    }

    protected function getFilterCallbackConfig(
        ContainerBuilder $container,
        Reference        $reference,
        array            $attributes,
    ): Reference {
        /** @see FlareCallbackConfig::__construct */
        $definition = new Definition(FlareCallbackConfig::class, [
            $reference,
            $attributes,
            $attributes['element'] ?? null,
            $attributes['target'] ?? null,
            $attributes['method'] ?? null,
            $attributes['priority'] ?? 0,
        ]);

        $serviceId = 'huh.flare.flare_callback._config_' . ContainerBuilder::hash($definition);
        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }
}