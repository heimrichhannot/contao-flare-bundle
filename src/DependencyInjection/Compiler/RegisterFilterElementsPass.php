<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
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

        $tag = FilterElementDescriptor::TAG;
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
                $alias = $this->getFilterElementAlias($definition, $attributes);
                $attributes['alias'] = $alias;

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
        /** @see \HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor::__construct */
        $definition = new Definition(FilterElementDescriptor::class, [
            $reference,
            $attributes,
            $attributes['palette'] ?? null,
            $attributes['formType'] ?? null,
            $attributes['method'] ?? null,
            $attributes['scopes'] ?? null,
            $attributes['isTargeted'] ?? null,
        ]);

        $serviceId = 'huh.flare.filter_element._config_' . ContainerBuilder::hash($definition);
        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    protected function getFilterElementAlias(Definition $definition, array $attributes): string
    {
        if ($alias = (string) ($attributes['alias'] ?? null))
        {
            if ($alias === 'default') {
                throw new \InvalidArgumentException('The filter element alias "default" is a reserved keyword.');
            }

            return $alias;
        }

        $className = $definition->getClass();
        $className = \ltrim(\strrchr($className, '\\'), '\\');
        $className = Str::trimSubstrings($className, suffix: ['Controller', 'FilterElement', 'Element']);

        return Container::underscore($className);
    }
}