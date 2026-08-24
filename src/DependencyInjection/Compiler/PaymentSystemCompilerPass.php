<?php

namespace IDCI\Bundle\PaymentBundle\DependencyInjection\Compiler;

use IDCI\Bundle\PaymentBundle\System\PaymentSystemRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PaymentSystemCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(PaymentSystemRegistry::class)) {
            return;
        }

        $registryDefinition = $container->getDefinition(PaymentSystemRegistry::class);

        $taggedServices = $container->findTaggedServiceIds('idci_payment.system');
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
