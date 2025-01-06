<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

class OgonePaymentGateway extends AbstractPaymentGateway
{
    /**
     * Get Ogone server url.
     *
     * @method getServerUrl
     */
    private function getServerUrl(): string
    {
        return 'https://secure.ogone.com/ncol/test/orderstandard.asp'; // raw (for test)
    }

    /**
     * Build payment gateway options.
     *
     * @method buildOptions
     */
    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'ACCEPTURL' => $paymentGatewayConfiguration->get('return_url'),
            'AMOUNT' => $transaction->getAmount(),
            'CANCELURL' => $paymentGatewayConfiguration->get('return_url'),
            //'CN' => '',
            'CURRENCY' => $transaction->getCurrencyCode(),
            'DECLINEURL' => $paymentGatewayConfiguration->get('return_url'),
            'EMAIL' => $transaction->getCustomerEmail(),
            'EXCEPTIONURL' => $paymentGatewayConfiguration->get('return_url'),
            'LANGUAGE' => 'fr_FR',
            'ORDERID' => $transaction->getId(),
            //'OWNERADDRESS' => '',
            //'OWNERCTY' => '',
            //'OWNERTELNO' => '',
            //'OWNERTOWN' => '',
            //'OWNERZIP' => '',
            'PSPID' => $paymentGatewayConfiguration->get('client_id'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        $shasign = '';

        foreach ($options as $key => $value) {
            if (!empty($value)) {
                $shasign .= sprintf('%s=%s%s', $key, $value, $paymentGatewayConfiguration->get('client_secret'));
            } else {
                unset($options[$key]);
            }
        }

        $options['SHASIGN'] = mb_strtoupper(sha1($shasign));

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/ogone.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return new GatewayResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'client_id',
                'client_secret',
            ]
        );
    }
}
