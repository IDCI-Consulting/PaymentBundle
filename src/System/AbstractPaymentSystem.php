<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

abstract class AbstractPaymentSystem implements PaymentSystemInterface
{
    protected Environment $templating;

    protected EventDispatcherInterface $dispatcher;

    public function __construct(Environment $templating, EventDispatcherInterface $eventDispatcher)
    {
        $this->templating = $templating;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function configureParameters(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('client_return_url')->setAllowedTypes('client_return_url', ['string'])
            ->setRequired('system_notification_url')->setAllowedTypes('system_notification_url', ['string'])
        ;
    }

    public function buildInitialHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);
        $resolvedParameters = $resolver->resolve($parameters);

        return $this->doBuildInitialHTMLView($transaction, $request, $resolvedParameters);
    }

    public function buildFeedbackHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);
        $resolvedParameters = $resolver->resolve($parameters);

        return $this->doBuildFeedbackHTMLView($transaction, $request, $resolvedParameters);
    }

    public function handleNotification(Transaction $transaction, Request $request, array $parameters): void
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);
        $resolvedParameters = $resolver->resolve($parameters);

        $this->doHandleNotification($transaction, $request, $resolvedParameters);
    }

    abstract public function doBuildInitialHTMLView(Transaction $transaction, Request $request, array $parameters): string;
    abstract public function doBuildFeedbackHTMLView(Transaction $transaction, Request $request, array $parameters): string;
    abstract public function doHandleNotification(Transaction $transaction, Request $request, array $parameters): void;
}