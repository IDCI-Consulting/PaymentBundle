<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var \Twig_Environment
     */
    protected $templating;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    public function __construct(\Twig_Environment $templating, UrlGeneratorInterface $router)
    {
        $this->templating = $templating;
        $this->router = $router;
    }

    public function getCallbackURL(string $alias): string
    {
        return $this->router->generate(
            'idci_payment_paymentgateway_callback',
            ['paymentGatewayConfigurationAlias' => $alias],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    abstract public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $payment
    ): string;

    abstract public function retrieveTransactionUuid(Request $request): ?string;

    abstract public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?bool;

    abstract public static function getParameterNames(): ?array;
}
