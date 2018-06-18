<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

interface PaymentContextInterface
{
    public function createTransaction(array $parameters): Transaction;

    public function buildHTMLView(): string;

    public function executeTransaction(Request $request): ?bool;

    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface;

    public function getPaymentGateway(): PaymentGatewayInterface;

    public function hasTransaction(): bool;

    public function getTransaction(): ?Transaction;

    public function setTransaction(Transaction $transaction): self;
}
