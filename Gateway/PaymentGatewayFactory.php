<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedPaymentException;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedPaymentGatewayException;

class PaymentGatewayFactory
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var array
     */
    private $paymentGatewayList;

    public function __construct(ObjectManager $om, array $paymentGatewayList)
    {
        $this->om = $om;
        $this->paymentGatewayList = $paymentGatewayList;
    }

    public function getPaymentGatewayList(): array
    {
        return $this->paymentGatewayList;
    }

    public function getPaymentGatewayFQCN(string $gatewayName): string
    {
        if (!isset($this->paymentGatewayList[$gatewayName])) {
            throw new UndefinedPaymentGatewayException(sprintf('No gateway exist for the gateway name : %s', $gatewayName));
        }

        return $this->paymentGatewayList[$gatewayName];
    }

    public function buildFromPaymentUuid(string $uuid): PaymentGatewayInterface
    {
        $payment = $this
            ->om
            ->getRepository(Payment::class)
            ->findOneBy(['id' => $uuid])
        ;

        if (null === $payment) {
            throw new UndefinedPaymentException(sprintf('No gateway exist for the alias : %s', $alias));
        }

        return $this
            ->buildFromAlias($payment->getGatewayConfigurationAlias())
            ->setPayment($payment)
        ;
    }

    public function buildFromAlias(string $alias): PaymentGatewayInterface
    {
        $paymentGatewayConfiguration = $this
            ->om
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findOneBy(['alias' => $alias])
        ;

        if (null === $paymentGatewayConfiguration) {
            throw new UndefinedPaymentGatewayException(sprintf('No gateway exist for the alias : %s', $alias));
        }

        return $this->buildFromPaymentGatewayConfiguration($paymentGatewayConfiguration);
    }

    public function buildFromPaymentGatewayConfiguration(
        PaymentGatewayConfiguration $paymentGatewayConfiguration
    ): PaymentGatewayInterface {
        $paymentGatewayFQCN = $this->getPaymentGatewayFQCN($paymentGatewayConfiguration->getGatewayName());

        return new $paymentGatewayFQCN($this->om, $paymentGatewayConfiguration);
    }
}
