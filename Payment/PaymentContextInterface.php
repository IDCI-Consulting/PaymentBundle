<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;

interface PaymentContextInterface
{
    public function createPayment(array $parameters): Payment;

    public function buildHTMLView(): string;

    public function executePayment(Request $request): ?bool;

    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface;

    public function getPaymentGateway(): PaymentGatewayInterface;

    public function hasPayment(): bool;

    public function getPayment(): ?Payment;

    public function setPayment(Payment $payment): self;
}
