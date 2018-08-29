<?php

namespace IDCI\Bundle\PaymentBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('idci_payment');

        $rootNode
            ->children()
                ->arrayNode('templates')
                    ->children()
                        ->arrayNode('step')
                            ->children()
                                ->scalarNode('failed')->end()
                                ->scalarNode('success')->end()
                                ->scalarNode('pending')->end()
                                ->scalarNode('initialize')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('enabled_doctrine_subscriber')->end()
                ->booleanNode('enabled_logger_subscriber')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
