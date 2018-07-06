<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var \Twig_Environment
     */
    protected $templating;

    public function __construct(\Twig_Environment $templating)
    {
        $this->templating = $templating;
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

    public static function getParameterNames(): ?array
    {
        return [
            'callback_url',
            'return_url',
        ];
    }
}
