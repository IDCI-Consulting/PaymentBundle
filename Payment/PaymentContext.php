<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;

class PaymentContext
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
     * @var Payment
     */
    private $payment;

    public function __construct(
        ObjectManager $om,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        PaymentGatewayInterface $paymentGateway,
        ?Payment $payment = null
    ) {
        $this->om = $om;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->paymentGateway = $paymentGateway;
        $this->payment = $payment;
    }

    public function createPayment(array $parameters): Payment
    {
        $parameters['gateway_configuration_alias'] = $this->paymentGatewayConfiguration->getAlias();

        $this->payment = PaymentFactory::getInstance()->create($parameters);

        $this->om->persist($this->payment);
        $this->om->flush();

        return $this->payment;
    }

    public function buildHTMLView(): string
    {
        return $this->paymentGateway->buildHTMLView($this->paymentGatewayConfiguration, $this->payment);
    }

    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface
    {
        return $this->paymentGatewayConfiguration;
    }

    public function getPaymentGateway(): PaymentGatewayInterface
    {
        return $this->paymentGateway;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): self
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * METHODS ONLY USED FOR TESTS.
     */
    public function preProcess(Request $request)
    {
        $this->paymentGateway->preProcess($request, $this->paymentGatewayConfiguration, $this->payment);
    }

    public function postProcess(Request $request)
    {
        $this->paymentGateway->postProcess($request, $this->paymentGatewayConfiguration, $this->payment);
    }
}
