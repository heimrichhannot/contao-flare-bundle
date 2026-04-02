<?php

declare(strict_types=1);

/**
 * Heimrich & Hannot Flare Bundle
 *
 * @copyright 2025 Heimrich & Hannot GmbH
 * @author    Eric Gesemann <e.gesemann@heimrich-hannot.de>
 * @license   LGPL-3.0-or-later
 */

namespace HeimrichHannot\FlareBundle\ContaoManager;

use Codefog\TagsBundle\CodefogTagsBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use HeimrichHannot\FlareBundle\HeimrichHannotFlareBundle;
use HeimrichHannot\FlareBundle\Util\Env;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

final class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        $loadAfter = [
            ContaoCoreBundle::class
        ];

        if (\class_exists(CodefogTagsBundle::class) && Env::hasCodefogTags()) {
            $loadAfter[] = CodefogTagsBundle::class;
        }

        return [
            BundleConfig::create(HeimrichHannotFlareBundle::class)->setLoadAfter($loadAfter),
        ];
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
    {
        return $resolver->resolve($routes = '@HeimrichHannotFlareBundle/config/routes.yaml')->load($routes);
    }
}