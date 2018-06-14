<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

interface PaymentGatewayInterface
{
    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string;

    public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool;

    public static function getParameterNames(): ?array;
}
