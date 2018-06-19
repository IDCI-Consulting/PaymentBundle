<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedTransactionException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistryInterface;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContext;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContextInterface;

class PaymentManager
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var PaymentGatewayRegistryInterface
     */
    private $paymentGatewayRegistry;

    public function __construct(ObjectManager $om, PaymentGatewayRegistryInterface $paymentGatewayRegistry)
    {
        $this->om = $om;
        $this->paymentGatewayRegistry = $paymentGatewayRegistry;
    }

    public function getAllPaymentGatewayConfiguration(): array
    {
        return $this
            ->om
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findAll()
        ;
    }

    public function createPaymentContextByAlias(string $alias): PaymentContextInterface
    {
        $paymentGatewayConfiguration = $this
            ->om
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findOneBy(['alias' => $alias])
        ;

        if (null === $paymentGatewayConfiguration) {
            throw new NoPaymentGatewayConfigurationFoundException();
        }

        return new PaymentContext(
            $this->om,
            $paymentGatewayConfiguration,
            $this->paymentGatewayRegistry->get($paymentGatewayConfiguration->getGatewayName())
        );
    }

    public function createPaymentContextByPaymentUuid(string $uuid): PaymentContextInterface
    {
        $transaction = $this
            ->om
            ->getRepository(Transaction::class)
            ->findOneBy(['id' => $uuid])
        ;

        if (null === $transaction) {
            throw new UndefinedTransactionException(sprintf('No transaction found with the uuid : %s', $uuid));
        }

        return $this
            ->createPaymentContextByAlias($payment->getGatewayConfigurationAlias())
            ->setTransaction($transaction)
        ;
    }
}
