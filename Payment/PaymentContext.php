<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\AlreadyDefinedTransactionException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\PaymentStatusCode;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

class PaymentContext implements PaymentContextInterface
{
    /**
     * @var ObjectManager
     */
    private $om;

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
     * @var Transaction
     */
    private $transaction;

    public function __construct(
        ObjectManager $om,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        PaymentGatewayInterface $paymentGateway,
        TransactionManagerInterface $transactionManager,
        ?Transaction $transaction = null
    ) {
        $this->om = $om;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->paymentGateway = $paymentGateway;
        $this->transactionManager = $transactionManager;
        $this->transaction = $transaction;
    }

    public function createTransaction(array $parameters): Transaction
    {
        $parameters['gateway_configuration_alias'] = $this->paymentGatewayConfiguration->getAlias();

        $this->transaction = TransactionFactory::getInstance()->create($parameters);

        $this->om->persist($this->transaction);
        $this->om->flush();

        return $this->transaction;
    }

    public function handleGatewayCallback(Request $request): Transaction
    {
        $gatewayResponse = $this
            ->paymentGateway
            ->getResponse($request, $this->paymentGatewayConfiguration)
        ;

        $status = $gatewayResponse->getStatus();

        $transaction = $this
            ->transactionManager
            ->retrieveTransactionByUuid($gatewayResponse->getTransactionUuid())
        ;

        if ($transaction->getAmount() != $gatewayResponse->getAmount()) {
            $status = PaymentStatusCode::STATUS_FAILED;
        }

        return $transaction->setStatus($gatewayResponse->getStatus());
    }

    public function hasTransaction(): bool
    {
        return isset($this->transaction);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function buildHTMLView(): string
    {
        return $this
            ->getPaymentGateway()
            ->buildHTMLView($this->getPaymentGatewayConfiguration(), $this->getTransaction())
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
        if ($this->hasPayment()) {
            throw new AlreadyDefinedTransactionException(
                sprintf('The payment context has already a transaction defined.')
            );
        }

        $this->transaction = $transaction;

        return $this;
    }
}
