<?php

namespace IDCI\Bundle\PaymentBundle\DependencyInjection;

use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
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
                                ->scalarNode(PaymentStatus::STATUS_FAILED)->end()
                                ->scalarNode(PaymentStatus::STATUS_APPROVED)->end()
                                ->scalarNode(PaymentStatus::STATUS_PENDING)->end()
                                ->scalarNode(PaymentStatus::STATUS_CREATED)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('gateway_configurations')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('gateway_name')->end()
                            ->booleanNode('enabled')->end()
                            ->arrayNode('parameters')
                                ->scalarPrototype()->end()
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
