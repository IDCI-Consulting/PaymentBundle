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
     *
     * @method initialize
     *
     * @param PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
     * @param Transaction                          $transaction
     *
     * @return array
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array;

    /**
     * Build payment gateway HTML view.
     *
     * @method buildHTMLView
     *
     * @param PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
     * @param Transaction                          $transaction
     *
     * @return string
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string;

    /**
     * Get payment gateway callback/notification response.
     *
     * @method getResponse
     *
     * @param Request                              $request
     * @param PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
     *
     * @return GatewayResponse
     */
    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse;

    /**
     * Get payment gateway authorized parameters names used in configuration.
     *
     * @method getParameterNames
     *
     * @return array|null
     */
    public static function getParameterNames(): ?array;
}
