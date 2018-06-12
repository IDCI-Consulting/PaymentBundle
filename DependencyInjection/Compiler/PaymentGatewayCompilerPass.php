<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */

namespace IDCI\Bundle\PaymentBundle\DependencyInjection\Compiler;

use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PaymentGatewayCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(PaymentGatewayInterface::class) ||
            !$container->hasDefinition(PaymentGatewayRegistry::class)
        ) {
            return;
        }

        $registryDefinition = $container->getDefinition(PaymentGatewayRegistry::class);

        $taggedServices = $container->findTaggedServiceIds('idci_payment.gateways');
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $registryDefinition->addMethodCall(
                    'set',
                    [
                        $attributes['alias'],
                        new Reference($id),
                    ]
                );
            }
        }
    }
}
