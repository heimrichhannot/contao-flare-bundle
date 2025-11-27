<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\EventListener\Integration\Terminal42ChangelanguageListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Terminal42\DcMultilingualBundle\QueryBuilder\MultilingualQueryBuilderFactoryInterface;

class Terminal24DcMultilingualPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(Terminal42ChangelanguageListener::class)) {
            return;
        }

        $factoryServiceId = MultilingualQueryBuilderFactoryInterface::class;

        if (!$container->hasDefinition($factoryServiceId)) {
            return;
        }

        $container
            ->getDefinition(Terminal42ChangelanguageListener::class)
            ->addMethodCall('setMultilingualQueryBuilderFactory', [new Reference($factoryServiceId)]);
    }
}
