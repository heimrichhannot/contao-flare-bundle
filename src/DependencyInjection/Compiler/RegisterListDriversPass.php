<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
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
                $type = $this->getListDriverName($definition, $attributes);

                /** @see AsListDriver::__construct */
                $attribute = new Definition(AsListDriver::class, [$type, $attributes['dataContainer'] ?? null]);

                /** @see ListDriverRegistry::add() */
                $registry->addMethodCall('add', [$reference, $attribute, $type]);

                $serviceId = 'flare.list_driver.' . $type;

                $container
                    ->setAlias($serviceId, (string) $reference)
                    ->setPublic(true);
            }
        }
    }

    protected function getListDriverName(Definition $definition, array $attributes): string
    {
        if ($type = (string) ($attributes['type'] ?? ''))
        {
            if ($type === 'default') {
                throw new \InvalidArgumentException('The list type name "default" is a reserved keyword.');
            }

            return $type;
        }

        return TypeNameFactory::createListDriverType($definition->getClass());
    }
}
