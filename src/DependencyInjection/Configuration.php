<?php

declare(strict_types=1);

namespace FroshTypesense\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('frosh_typesense');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('token')->end()
                ->arrayNode('hosts')
                    ->scalarPrototype()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
