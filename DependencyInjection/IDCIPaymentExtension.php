<?php

namespace IDCI\Bundle\PaymentBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class IDCIPaymentExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('idci_payment.enabled_logger_subscriber', $config['enabled_logger_subscriber']);
        $container->setParameter('idci_payment.gateway_configurations', $config['gateway_configurations']);

        if (isset($config['templates'])) {
            $container->setParameter('idci_payment.templates.step', $config['templates']['step']);
        }
    }
}
