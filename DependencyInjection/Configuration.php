<?php

namespace IDCI\Bundle\PaymentBundle\DependencyInjection;

use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('idci_payment');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('templates')
                    ->children()
                        ->arrayNode('step')
                            ->children()
                                ->scalarNode(PaymentStatus::STATUS_APPROVED)->end()
                                ->scalarNode(PaymentStatus::STATUS_CANCELED)->end()
                                ->scalarNode(PaymentStatus::STATUS_CREATED)->end()
                                ->scalarNode(PaymentStatus::STATUS_FAILED)->end()
                                ->scalarNode(PaymentStatus::STATUS_PENDING)->end()
                                ->scalarNode(PaymentStatus::STATUS_UNVERIFIED)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('gateway_configurations')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('gateway_name')->isRequired()->cannotBeEmpty()->end()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->arrayNode('parameters')->variablePrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('enabled_logger_subscriber')->defaultFalse()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
