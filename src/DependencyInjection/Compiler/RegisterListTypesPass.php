<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
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

        $tag = ListTypeDescriptor::TAG;
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
                $type = $this->getListTypeName($definition, $attributes);
                $attributes['type'] = $type;

                $serviceId = 'huh.flare.list_type.' . $type;

                $childDefinition = new ChildDefinition((string) $reference);
                $childDefinition->setPublic(true);

                $config = $this->getListTypeConfig($container, $reference, $attributes);

                /** @see FilterElementRegistry::add() */
                $registry->addMethodCall('add', [$type, $config]);

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
        /** @see ListTypeDescriptor::__construct */
        $definition = new Definition(ListTypeDescriptor::class, [
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

    protected function getListTypeName(Definition $definition, array $attributes): string
    {
        if ($type = (string) ($attributes['type'] ?? ''))
        {
            if ($type === 'default') {
                throw new \InvalidArgumentException('The list type name "default" is a reserved keyword.');
            }

            return $type;
        }

        $className = $definition->getClass();
        $className = \ltrim(\strrchr($className, '\\'), '\\');
        $className = Str::trimSubstrings($className, suffix: ['ListType', 'Type']);

        return Container::underscore($className);
    }
}