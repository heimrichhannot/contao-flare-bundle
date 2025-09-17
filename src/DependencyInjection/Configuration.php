<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('huh_flare');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('format_label_defaults')
                    ->useAttributeAsKey('key')
                    ->prototype('scalar')->end()
                    ?->validate()
                        ->ifTrue(function ($value) {
                            foreach ($value as $k => $v) {
                                if (!\is_string($k) || !\is_string($v) || !\str_starts_with($k, 'tl_')) {
                                    return true;
                                }
                            }
                            return false;
                        })
                        ->thenInvalid('All keys under "format_label_defaults" must start with "tl_".')
                    ->end()
                ->end()
            ?->end();

        return $treeBuilder;
    }
}