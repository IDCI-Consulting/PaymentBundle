<?php

namespace IDCI\Bundle\PaymentBundle\Context;

use IDCI\Bundle\PaymentBundle\Gateway\PaymentGateway;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\System\PaymentSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentContext
{
    private EventDispatcherInterface $eventDispatcher;

    private LoggerInterface $logger;

    private Request $request;

    private PaymentGateway $paymentGateway;

    private Transaction $transaction;

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getPaymentGateway(): PaymentGateway
    {
        return $this->paymentGateway;
    }

    public function setPaymentGateway(PaymentGateway $paymentGateway): self
    {
        $this->paymentGateway = $paymentGateway;

        return $this;
    }

    public function getPaymentSystem(): PaymentSystemInterface
    {
        return $this->getPaymentGateway()->getPaymentSystem();
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function createTransaction(array $data): Transaction
    {
        $resolver = new OptionsResolver();
        $this->configureTransactionData($resolver);
        $resolvedData = $resolver->resolve($data);

        $transaction = (new Transaction())
            ->setId($resolvedData['id'])
            ->setNumber($resolvedData['number'])
            ->setPaymentGatewayConfigurationAlias($resolvedData['payment_gateway_configuration_alias'])
            ->setPaymentMethod($resolvedData['payment_method'])
            ->setItemReference($resolvedData['item_reference'])
            ->setCustomerReference($resolvedData['customer_reference'])
            ->setCustomerEmail($resolvedData['customer_email'])
            ->setStatus($resolvedData['status'])
            ->setAmount($resolvedData['amount'])
            ->setCurrencyCode($resolvedData['currency_code'])
            ->setDescription($resolvedData['description'])
            ->setMetadata($resolvedData['metadata'])
        ;

        $this->setTransaction($transaction);

        return $transaction;
    }

    public function configureTransactionData(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('id')->setAllowedTypes('id', ['string'])
            ->setDefault('number', null)->setAllowedTypes('number', ['null', 'int'])
                ->setNormalizer('number', function(Options $options, $value): ?int {
                    if (is_string($value)) {
                        return (int)$value;
                    }

                    return $value;
                })
            ->setRequired('payment_gateway_configuration_alias')->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setDefault('payment_method', null)->setAllowedTypes('payment_method', ['null', 'string'])
            ->setRequired('item_reference')->setAllowedTypes('item_reference', ['string'])
            ->setRequired('customer_reference')->setAllowedTypes('customer_reference', ['string'])
            ->setDefault('customer_email', null)->setAllowedTypes('customer_email', ['null', 'string'])
            ->setDefault('status', null)->setAllowedTypes('status', ['null', 'string'])
            ->setRequired('amount')->setAllowedTypes('amount', ['int', 'string'])
                ->setNormalizer('amount', function(Options $options, $value): int {
                    if (is_string($value)) {
                        return (int)$value;
                    }

                    return $value;
                })
            ->setRequired('currency_code')->setAllowedTypes('currency_code', ['string'])
            ->setDefault('description', null)->setAllowedTypes('description', ['null', 'string'])
            ->setDefault('metadata', [])->setAllowedTypes('metadata', ['array'])
        ;
    }

    public function buildInitialHTMLView(): string
    {
        $resolver = new OptionsResolver();
        $this->getPaymentSystem()->configureParameters($resolver);
        $parameters = $resolver->resolve($this->getPaymentGateway()->getParameters());

        return $this->getPaymentSystem()->buildInitialHTMLView($this->getTransaction(), $this->getRequest(), $parameters);
    }
}