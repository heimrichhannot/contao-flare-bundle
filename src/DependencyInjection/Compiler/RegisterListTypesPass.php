<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\List\ListTypeConfig;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterListTypesPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ListTypeRegistry::class)) {
            return;
        }

        $tag = ListTypeConfig::TAG;
        $registry = $container->findDefinition(ListTypeRegistry::class);

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            if (\str_starts_with((string) $reference, 'huh.flare.list_type._')) {
                continue;
            }

            $definition = $container->findDefinition((string) $reference);
            $tags = $definition->getTag($tag);
            $definition->clearTag($tag);

            foreach ($tags as $attributes)
            {
                $alias = $attributes['alias'] = $this->getListTypeAlias($definition, $attributes);

                $serviceId = 'huh.flare.list_type.' . $alias;

                $childDefinition = new ChildDefinition((string) $reference);
                $childDefinition->setPublic(true);

                $config = $this->getListTypeConfig($container, $reference, $attributes);

                /** @see FilterElementRegistry::add() */
                $registry->addMethodCall('add', [$alias, $config]);

                $childDefinition->setTags($definition->getTags());
                $container->setDefinition($serviceId, $childDefinition);
            }
        }
    }

    protected function getListTypeConfig(
        ContainerBuilder $container,
        Reference        $reference,
        array            $attributes
    ): Reference {
        /** @see ListTypeConfig::__construct */
        $definition = new Definition(ListTypeConfig::class, [
            $reference,
            $attributes,
            $attributes['dataContainer'] ?? null,
            $attributes['palette'] ?? null,
            $attributes['method'] ?? null,
        ]);

        $serviceId = 'huh.flare.list_type._config_' . ContainerBuilder::hash($definition);
        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    protected function getListTypeAlias(Definition $definition, array $attributes): string
    {
        if (!empty($attributes['alias']))
        {
            $alias = (string) $attributes['alias'];

            if ($alias === 'default') {
                throw new \InvalidArgumentException('The list type alias "default" is a reserved keyword.');
            }

            return $alias;
        }

        $className = $definition->getClass();
        $className = \ltrim(\strrchr($className, '\\'), '\\');
        $className = Str::trimSubstrings($className, suffix: ['ListType', 'Type']);

        return Container::underscore($className);
    }
}