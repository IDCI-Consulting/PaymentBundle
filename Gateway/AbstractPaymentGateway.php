<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;

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

    abstract public static function getParameterNames(): ?array;
}
