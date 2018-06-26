<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
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

    /**
     * @var TransactionManagerInterface
     */
    protected $transactionManager;

    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        TransactionManagerInterface $transactionManager
    ) {
        $this->templating = $templating;
        $this->router = $router;
        $this->transactionManager = $transactionManager;
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
        Transaction $transaction
    ): string;

    abstract public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse;

    abstract public static function getParameterNames(): ?array;
}
