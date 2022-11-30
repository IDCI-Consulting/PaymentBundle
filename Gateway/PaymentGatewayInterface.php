<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

interface PaymentGatewayInterface
{
    /**
     * Initialize payment gateway configuration.
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array;

    /**
     * Build payment gateway HTML view.
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string;

    /**
     * Get payment gateway return response.
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse;

    /**
     * Get payment gateway callback/notification response.
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse;

    /**
     * Get payment gateway authorized parameters names used in configuration.
     *
     * @method getParameterNames
     */
    public static function getParameterNames(): ?array;
}
