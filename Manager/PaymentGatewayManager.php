<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedPaymentException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistry;

class PaymentGatewayManager
{
    private $om;
    private $paymentGatewayRegistry;

    public function __construct(ObjectManager $om, PaymentGatewayRegistry $paymentGatewayRegistry)
    {
        $this->om = $om;
        $this->paymentGatewayRegistry = $paymentGatewayRegistry;
    }

    public function getByAlias(string $alias): PaymentGatewayInterface
    {
        $paymentGatewayConfiguration = $this
            ->om
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findOneBy(['alias' => 'stripe_test']) // raw alias
        ;

        if (null === $paymentGatewayConfiguration) {
            throw new NoPaymentGatewayConfigurationFoundException();
        }

        return $this
            ->paymentGatewayRegistry
            ->get($paymentGatewayConfiguration->getGatewayName())
            ->setPaymentGatewayConfiguration($paymentGatewayConfiguration)
        ;
    }

    public function getByPaymentUuid(string $uuid): PaymentGatewayInterface
    {
        $payment = $this
            ->om
            ->getRepository(Payment::class)
            ->findOneBy(['id' => $uuid])
        ;

        if (null === $payment) {
            throw new UndefinedPaymentException(sprintf('No payment found with the uuid : %s', $uuid));
        }

        // TEMP set payment directly on gateway for testing >
        $paymentGateway = $this->getByAlias($payment->getGatewayConfigurationAlias());
        $paymentGateway->payment = $payment;
        // < set payment directly on gateway for testing TEMP

        return $paymentGateway;
    }
}
