<?php

/*
 * @copyright Copyright (c) 2025, Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FlareBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotFlareBundle extends Bundle
{
    /**
     * @{inheritdoc}
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * {@inheritdoc}
     * @return class-string<ExtensionInterface>
     */
    public function getContainerExtensionClass(): string
    {
        return DependencyInjection\HeimrichHannotFlareExtension::class;
    }

    public function getContainerExtension(): false|ExtensionInterface|null
    {
        $this->extension ??= $this->createContainerExtension() ?? false;
        return $this->extension ?: null;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DependencyInjection\Compiler\RegisterFilterElementsPass());
    }
}