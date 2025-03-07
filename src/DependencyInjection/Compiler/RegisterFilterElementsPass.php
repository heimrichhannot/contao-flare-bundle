<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Filter\FilterElementConfig;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterFilterElementsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(FilterElementRegistry::class)) {
            return;
        }

        $tag = FilterElementConfig::TAG;
        $registry = $container->findDefinition(FilterElementRegistry::class);

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            if (\str_starts_with((string) $reference, 'huh.flare.filter_element._')) {
                continue;
            }

            $definition = $container->findDefinition((string) $reference);
            $tags = $definition->getTag($tag);
            $definition->clearTag($tag);

            foreach ($tags as $attributes)
            {
                $alias = $attributes['alias'] = $this->getFilterElementAlias($definition, $attributes);

                $serviceId = 'huh.flare.filter_element.' . $alias;

                $childDefinition = new ChildDefinition((string) $reference);
                $childDefinition->setPublic(true);

                $config = $this->getFilterElementConfig($container, $reference, $attributes);

                /** @see FilterElementRegistry::add() */
                $registry->addMethodCall('add', [$alias, $config]);

                $childDefinition->setTags($definition->getTags());
                $container->setDefinition($serviceId, $childDefinition);
            }
        }
    }

    protected function getFilterElementConfig(
        ContainerBuilder $container,
        Reference        $reference,
        array            $attributes
    ): Reference {
        /** @see FilterElementConfig::__construct */
        $definition = new Definition(FilterElementConfig::class, [
            $reference,
            $attributes,
            $attributes['palette'] ?? null,
            $attributes['formType'] ?? null,
            $attributes['method'] ?? null,
        ]);

        $serviceId = 'huh.flare.filter_element._config_' . ContainerBuilder::hash($definition);
        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    protected function getFilterElementAlias(Definition $definition, array $attributes): string
    {
        if (!empty($attributes['alias'])) {
            return (string) $attributes['alias'];
        }

        $className = $definition->getClass();
        $className = \ltrim(\strrchr($className, '\\'), '\\');
        $className = Str::trimSubstrings($className, suffix: ['Controller', 'FilterElement', 'Element']);

        return Container::underscore($className);
    }
}