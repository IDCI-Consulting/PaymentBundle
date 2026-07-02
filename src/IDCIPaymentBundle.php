<?php

namespace IDCI\Bundle\PaymentBundle;

use IDCI\Bundle\PaymentBundle\DependencyInjection\Compiler\PaymentGatewayCompilerPass;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class IDCIPaymentBundle extends AbstractBundle
{
    protected string $extensionAlias = 'idci_payment';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
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
                ->booleanNode('enabled_logger_subscriber')->defaultFalse()->end()
                ->arrayNode('gateway_configurations')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('gateway_name')->isRequired()->cannotBeEmpty()->end()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->arrayNode('parameters')->variablePrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $builder->setParameter('idci_payment.enabled_logger_subscriber', $config['enabled_logger_subscriber']);
        $builder->setParameter('idci_payment.gateway_configurations', $config['gateway_configurations']);

        if (isset($config['templates'])) {
            $builder->setParameter('idci_payment.templates.step', $config['templates']['step']);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new PaymentGatewayCompilerPass());
    }
}
