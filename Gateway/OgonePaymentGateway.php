<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;

class OgonePaymentGateway extends AbstractPaymentGateway
{
    private function getServerUrl(): string
    {
        return 'https://secure.ogone.com/ncol/test/orderstandard.asp'; // raw (for test)
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackUrl = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());

        return [
            'ACCEPTURL' => $callbackUrl,
            'AMOUNT' => $transaction->getAmount(),
            'CANCELURL' => $callbackUrl,
            //'CN' => '',
            'CURRENCY' => $transaction->getCurrencyCode(),
            'DECLINEURL' => $callbackUrl,
            'EMAIL' => $transaction->getCustomerEmail(),
            'EXCEPTIONURL' => $callbackUrl,
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

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
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

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/ogone.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return null;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
        ];
    }
}
