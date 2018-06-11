<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Payment\PaymentFactory;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    //private $returnUrl;

    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;

    /**
     * @var PaymentGatewayConfiguration
     */
    protected $paymentGatewayConfiguration;

    /**
     * @var Payment
     */
    protected $payment;

    public function __construct(
        ObjectManager $om,
        PaymentFactory $paymentFactory,
        PaymentGatewayConfiguration $paymentGatewayConfiguration,
        ?Payment $payment = null
    ) {
        $this->om = $om;
        $this->paymentFactory = $paymentFactory;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->payment = $payment;
    }

    public function createPayment(?array $parameters): Payment
    {
        if (null === $this->payment) {
            $this->payment = $this->paymentFactory
                ->create($parameters)
                ->setGatewayConfigurationAlias($this->paymentGatewayConfiguration->getAlias())
            ;

            $this->om->persist($this->payment);
            $this->om->flush();
        }

        return $this->payment;
    }

    abstract public function buildHTMLView(): string;

    abstract public static function getParameterNames(): ?array;
}
