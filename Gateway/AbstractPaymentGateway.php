<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
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

    abstract public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string;

    abstract public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool;

    abstract public static function getParameterNames(): ?array;
}
