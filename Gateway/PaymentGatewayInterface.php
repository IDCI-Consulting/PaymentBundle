<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

interface PaymentGatewayInterface
{
    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string;

    public function callback(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?Transaction;

    public static function getParameterNames(): ?array;
}
