<?php

namespace IDCI\Bundle\PaymentBundle\Context;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Factory\TransactionFactory;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGateway;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\System\PaymentSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentContext
{
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private Request $request;
    private UrlGeneratorInterface $urlGenerator;
    private TransactionManagerInterface $transactionManager;
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

    public function getUrlGenerator(): UrlGeneratorInterface
    {
        return $this->urlGenerator;
    }

    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): self
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    public function getTransactionManager(): TransactionManagerInterface
    {
        return $this->transactionManager;
    }

    public function setTransactionManager(TransactionManagerInterface $transactionManager): self
    {
        $this->transactionManager = $transactionManager;

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

    public function retrieveTransactionByReference(string $reference): Transaction
    {
        $transaction = $this->getTransactionManager()->retrieveTransactionByReference($reference);
        $this->setTransaction($transaction);

        return $transaction;
    }

    public function buildInitialHTMLView(array $parameters = []): string
    {
        $mergedParameters = array_merge(
            $this->getPaymentGateway()->getParameters(),
            $parameters
        );

        // Generate default value for 'client_return_url' parameter based on the current request
        if (null === $mergedParameters['client_return_url']) {
            $mergedParameters['client_return_url'] = $this->urlGenerator->generate(
                $this->getRequest()->attributes->get('_route'),
                $this->getRequest()->attributes->get('_route_params'),
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        // Generate default value for 'system_notification_url' parameter
        if (null === $mergedParameters['system_notification_url']) {
            $mergedParameters['system_notification_url'] = $this->urlGenerator->generate(
                'idci_payment_gateway_transaction_notification',
                [
                    'configuration_alias' => $this->getPaymentGateway()->getAlias()
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $this->getPaymentSystem()->buildInitialHTMLView($this->getTransaction(), $this->getRequest(), $mergedParameters);
    }
}