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

    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var \Twig_Environment
     */
    protected $templating;

    public function __construct(ObjectManager $om, \Twig_Environment $templating)
    {
        $this->om = $om;
        $this->templating = $templating;
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
