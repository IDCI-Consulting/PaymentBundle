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
       return $this->doBuildInitialHTMLView($transaction, $request, $this->resolveParameters($parameters));
    }

    public function buildFinalHTMLView(Transaction $transaction, Request $request, array $parameters): string
    {
        $view = $this->doBuildFinalHTMLView($transaction, $request, $this->resolveParameters($parameters));

        if (null !== $view) {
            return $view;
        }

        return $this->templating->render(sprintf('@IDCIPayment/TransactionStatus/%s.html.twig', $transaction->getStatus()), [
            'transaction' => $transaction,
        ]);
    }

    public function handleNotification(Transaction $transaction, Request $request, array $parameters): void
    {
        $this->doHandleNotification($transaction, $request, $this->resolveParameters($parameters));
    }

    private function resolveParameters(array $parameters): array
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);

        return $resolver->resolve($parameters);
    }

    abstract public function doBuildInitialHTMLView(Transaction $transaction, Request $request, array $parameters): string;
    abstract public function doBuildFinalHTMLView(Transaction $transaction, Request $request, array $parameters): ?string;
    abstract public function doHandleNotification(Transaction $transaction, Request $request, array $parameters): void;
}