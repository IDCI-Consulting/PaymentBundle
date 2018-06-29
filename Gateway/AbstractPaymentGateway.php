<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

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

    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router
    ) {
        $this->templating = $templating;
        $this->router = $router;
    }

    protected function getCallbackURL(string $alias, ?array $parameters = []): string
    {
        $parameters['paymentGatewayConfigurationAlias'] = $alias;

        return $this->router->generate(
            'idci_payment_paymentgateway_callback',
            $parameters,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function getReturnURL(string $alias, ?array $parameters = [])
    {
        $parameters['configuration_alias'] = $alias;

        return $this->router->generate(
            'idci_payment_test_paymentgatewayfronttest_done',
            $parameters,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    abstract public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array;

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
