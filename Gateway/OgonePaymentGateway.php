<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

class OgonePaymentGateway extends AbstractPaymentGateway
{
    private function getServerUrl(): string
    {
        return 'https://secure.ogone.com/ncol/test/orderstandard.asp'; // raw (for test)
    }

    private function buildOptions(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): array
    {
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

    private function initializeGateway(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction)
    {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        $options['SHASIGN'] = '';

        foreach ($options as $key => $value) {
            $options['SHASIGN'] .= sprintf('%s=%s%s', $key, $value, $paymentGatewayConfiguration->get('client_secret'));
        }

        $options['SHASIGN'] = sha1($options['SHASIGN']);

        return $options;
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $initializationData = $this->initializeGateway($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/ogone.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        return null;
    }

    public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?bool {
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
