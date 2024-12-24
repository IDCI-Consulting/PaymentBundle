<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentContext implements PaymentContextInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var PaymentGatewayConfigurationInterface
     */
    private $paymentGatewayConfiguration;

    /**
     * @var PaymentGatewayInterface
     */
    private $paymentGateway;

    /**
     * @var TransactionManagerInterface
     */
    private $transactionManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Transaction
     */
    private $transaction;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        PaymentGatewayInterface $paymentGateway,
        TransactionManagerInterface $transactionManager,
        LoggerInterface $logger,
        ?Transaction $transaction = null
    ) {
        $this->dispatcher = $dispatcher;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->paymentGateway = $paymentGateway;
        $this->transactionManager = $transactionManager;
        $this->logger = $logger;
        $this->transaction = $transaction;
    }

    public function createTransaction(array $parameters): Transaction
    {
        $parameters['gateway_configuration_alias'] = $this->paymentGatewayConfiguration->getAlias();

        $this->transaction = TransactionFactory::getInstance()->create($parameters);

        $this->dispatcher->dispatch(new TransactionEvent($this->transaction), TransactionEvent::CREATED);

        return $this->transaction;
    }

    public function handleReturnCallback(Request $request): ?Transaction
    {
        $gatewayResponse = $this
            ->paymentGateway
            ->getReturnResponse($request, $this->paymentGatewayConfiguration)
        ;

        if (null === $gatewayResponse->getTransactionUuid()) {
            return null;
        }

        $transaction = $this
            ->transactionManager
            ->retrieveTransactionByUuid($gatewayResponse->getTransactionUuid())
        ;

        if (null !== $gatewayResponse->getStatus()) {
            $transaction->setStatus($gatewayResponse->getStatus());
        }

        return $transaction;
    }

    public function handleGatewayCallback(Request $request): Transaction
    {
        $gatewayResponse = $this
            ->paymentGateway
            ->getCallbackResponse($request, $this->paymentGatewayConfiguration)
        ;

        if (null === $gatewayResponse->getTransactionUuid()) {
            throw new \UnexpectedValueException('No transaction uuid found for this callback');
        }

        $transaction = $this
            ->transactionManager
            ->retrieveTransactionByUuid($gatewayResponse->getTransactionUuid())
        ;

        $status = $gatewayResponse->getStatus();

        if ($transaction->getAmount() != $gatewayResponse->getAmount()) {
            $status = PaymentStatus::STATUS_FAILED;
        } elseif (
            null !== $gatewayResponse->getCurrencyCode() &&
            $transaction->getCurrencyCode() !== $gatewayResponse->getCurrencyCode()
        ) {
            $status = PaymentStatus::STATUS_FAILED;
        }

        if ($this->logger) {
            try {
                $this->logger->info('Gateway response: ', [
                    'response' => json_encode($gatewayResponse->toArray()),
                ]);
            } catch (\Exception $e) {
            }
        }

        return $transaction
            ->setStatus($status)
            ->setPaymentMethod($gatewayResponse->getPaymentMethod())
            ->setRaw($gatewayResponse->getRaw())
        ;
    }

    public function hasTransaction(): bool
    {
        return isset($this->transaction);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function buildHTMLView(array $options = []): string
    {
        if (!$this->hasTransaction()) {
            throw new \LogicException('Payment context : No transaction has been initialized for this context.');
        }

        return $this
            ->getPaymentGateway()
            ->buildHTMLView($this->getPaymentGatewayConfiguration(), $this->getTransaction(), $options)
        ;
    }

    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface
    {
        return $this->paymentGatewayConfiguration;
    }

    public function getPaymentGateway(): PaymentGatewayInterface
    {
        return $this->paymentGateway;
    }

    public function setTransaction(Transaction $transaction): PaymentContextInterface
    {
        if ($this->hasTransaction()) {
            throw new \LogicException('Payment context : The payment context already has a transaction defined.');
        }

        $this->transaction = $transaction;

        return $this;
    }
}
