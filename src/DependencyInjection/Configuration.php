<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
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
                    ->validate()
                        ->ifTrue(static function (array $value): bool {
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
                ->arrayNode('search_stop_words')
                    ->useAttributeAsKey('locale')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static function (array $value): bool {
                            foreach ($value as $locale => $words) {
                                if (!\is_string($locale) || !\is_array($words)) {
                                    return true;
                                }

                                foreach ($words as $word) {
                                    if (!\is_string($word)) {
                                        return true;
                                    }
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Each "search_stop_words" entry must be a locale key containing an array of strings.')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}