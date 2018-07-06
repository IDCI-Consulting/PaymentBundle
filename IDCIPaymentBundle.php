<?php

namespace IDCI\Bundle\PaymentBundle;

use IDCI\Bundle\PaymentBundle\DependencyInjection\Compiler\PaymentGatewayCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class IDCIPaymentBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new PaymentGatewayCompilerPass());
    }
}
