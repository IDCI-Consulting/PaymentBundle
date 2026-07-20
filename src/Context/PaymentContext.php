<?php

namespace IDCI\Bundle\PaymentBundle\Context;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Factory\TransactionFactory;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGateway;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\System\PaymentSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
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
        $transaction = TransactionFactory::getInstance()->create($data);

        $this->getEventDispatcher()->dispatch(new TransactionEvent($transaction), TransactionEvent::CREATED);

        $this->setTransaction($transaction);

        return $transaction;
    }

    public function buildInitialHTMLView(): string
    {
        $resolver = new OptionsResolver();
        $this->getPaymentSystem()->configureParameters($resolver);
        $parameters = $resolver->resolve($this->getPaymentGateway()->getParameters());

        return $this->getPaymentSystem()->buildInitialHTMLView($this->getTransaction(), $this->getRequest(), $parameters);
    }
}