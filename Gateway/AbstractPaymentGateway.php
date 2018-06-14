<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    public function __construct(ObjectManager $om, \Twig_Environment $templating, UrlGeneratorInterface $router)
    {
        $this->om = $om;
        $this->templating = $templating;
        $this->router = $router;
    }

    abstract public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string;

    abstract public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool;

    abstract public static function getParameterNames(): ?array;
}
