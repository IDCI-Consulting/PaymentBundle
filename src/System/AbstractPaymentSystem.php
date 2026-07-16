<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractPaymentSystem implements PaymentSystemInterface
{
    public function configureParameters(OptionsResolver $resolver): void
    {

    }

    public function buildInitialHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        return '';
    }

    public function buildFeedbackHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        return '';
    }

    public function handleNotification(Transaction $transaction, Request $request, array $parameters): void
    {

    }
}