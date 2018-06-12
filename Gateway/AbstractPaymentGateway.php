<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Payment\PaymentFactory;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var PaymentGatewayConfiguration
     */
    protected $paymentGatewayConfiguration;

    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
    }

    public function setPaymentGatewayConfiguration(
        PaymentGatewayConfiguration $paymentGatewayConfiguration
    ): PaymentGatewayInterface {
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;

        return $this;
    }

    public function createPayment(?array $parameters): Payment
    {
        $payment = PaymentFactory::getInstance()
            ->create($parameters)
            ->setGatewayConfigurationAlias($this->paymentGatewayConfiguration->getAlias())
        ;

        $this->om->persist($payment);
        $this->om->flush();

        return $payment;
    }

    abstract public function buildHTMLView(Payment $payment): string;

    abstract public static function getParameterNames(): ?array;
}
