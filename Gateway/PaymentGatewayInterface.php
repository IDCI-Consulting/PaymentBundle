<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;

interface PaymentGatewayInterface
{
    public static function getParameterNames(): ?array;

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string;
}
