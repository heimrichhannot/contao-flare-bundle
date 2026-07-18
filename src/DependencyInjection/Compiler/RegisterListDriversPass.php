<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class RegisterListDriversPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ListDriverRegistry::class)) {
            return;
        }

        $tag = AsListDriver::TAG;
        $registry = $container->findDefinition(ListDriverRegistry::class);

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            $definition = $container->findDefinition((string) $reference);
            $tags = $definition->getTag($tag);
            $definition->clearTag($tag);

            foreach ($tags as $attributes)
            {
                $type = $this->getListTypeName($definition, $attributes);

                $serviceId = 'huh.flare.list_driver.' . $type;

                $childDefinition = new ChildDefinition((string) $reference);
                $childDefinition->setPublic(true);

                /** @see AsListDriver::__construct */
                $attribute = new Definition(AsListDriver::class, [$type, $attributes['dataContainer'] ?? null]);

                /** @see ListDriverRegistry::add() */
                $registry->addMethodCall('add', [$reference, $attribute, $type]);

                $childDefinition->setTags($definition->getTags());
                $container->setDefinition($serviceId, $childDefinition);
            }
        }
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
        $className = Str::trimSubstrings($className, suffix: ['ListDriver', 'Driver']);

        return Container::underscore($className);
    }
}
