<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

abstract class AbstractPaymentSystem implements PaymentSystemInterface
{
    protected Environment $templating;

    public function __construct(Environment $templating)
    {
        $this->templating = $templating;
    }

    public function configureParameters(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('client_return_url', null)->setAllowedTypes('client_return_url', ['null', 'string'])
            ->setDefault('system_notification_url', null)->setAllowedTypes('system_notification_url', ['null', 'string'])
        ;
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
        return;
    }
}