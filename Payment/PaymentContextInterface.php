<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

interface PaymentContextInterface
{
    public function createTransaction(array $parameters): Transaction;

    public function handleReturnCallback(Request $request): ?Transaction;

    public function handleGatewayCallback(Request $request): ?Transaction;

    public function hasTransaction(): bool;

    public function setTransaction(Transaction $transaction): self;

    public function getTransaction(): ?Transaction;

    public function buildHTMLView(array $options = []): string;

    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface;

    public function getPaymentGateway(): PaymentGatewayInterface;
}
