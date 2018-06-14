<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedPaymentException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistryInterface;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContext;

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

    public function createPaymentContextByAlias(string $alias): PaymentContext
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

    public function createPaymentContextByPaymentUuid(string $uuid): PaymentContext
    {
        $payment = $this
            ->om
            ->getRepository(Payment::class)
            ->findOneBy(['id' => $uuid])
        ;

        if (null === $payment) {
            throw new UndefinedPaymentException(sprintf('No payment found with the uuid : %s', $uuid));
        }

        return $this
            ->createPaymentContextByAlias($payment->getGatewayConfigurationAlias())
            ->setPayment($payment)
        ;
    }
}
