<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

interface PaymentSystemInterface
{
    public function configureParameters(OptionsResolver $resolver): void;

    public function isReturnClientRequest(Request $request): bool;

    public function initializeTransaction(Transaction $transaction, Request $request): Transaction;

    public function retrieveTransaction(Request $request, array $gatewayParameters): ?Transaction;

    public function processTransaction(Transaction $transaction, Request $request, array $parameters): ProcessedTransactionResult;

    public function handleNotification(Transaction $transaction, Request $request, array $parameters): void;
}