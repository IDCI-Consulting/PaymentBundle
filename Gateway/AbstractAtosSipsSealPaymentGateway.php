<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\InvalidTransactionException;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractAtosSipsSealPaymentGateway extends AbstractPaymentGateway
{
    // Status

    const STATUS_SUCCESS = 'SUCCESS';

    const STATUS_3D_SUCCESS = '3D_SUCCESS';

    abstract protected function getServerUrl(): string;

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackRoute = $this->router->generate(
            'idci_payment_paymentgateway_callback',
            ['paymentGatewayConfigurationAlias' => $paymentGatewayConfiguration->getAlias()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ); // in top level ?

        $options = [
            'amount' => $transaction->getAmount(),
            'automaticResponseUrl' => $callbackRoute,
            'captureDay' => $paymentGatewayConfiguration->get('capture_day'),
            'captureMode' => $paymentGatewayConfiguration->get('capture_mode'),
            'currencyCode' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'customerContact.email' => $transaction->getCustomerEmail(),
            'merchantId' => $paymentGatewayConfiguration->get('merchant_id'),
            'normalReturnUrl' => $callbackRoute,
            'orderId' => $transaction->getItemId(),
            'transactionReference' => $transaction->getId(),
            'keyVersion' => $paymentGatewayConfiguration->get('version'),
            //'paypageData.bypassReceiptPage' => 'Y',
        ];

        return [
            'options' => $options,
            'build' => implode('|', array_map(
                function ($k, $v) {
                    if (null !== $v) {
                        return sprintf('%s=%s', $k, $v);
                    }
                },
                array_keys($options),
                $options
            )),
            'secret' => $paymentGatewayConfiguration->get('secret'),
        ];
    }

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $builtOptions = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/atos_sips_seal.html.twig', [
            'url' => $this->getServerUrl(),
            'build' => $builtOptions['build'],
            'seal' => hash('sha256', mb_convert_encoding($builtOptions['build'].$builtOptions['secret'], 'UTF-8')),
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        $datas = explode('|', $request->get('Data'));

        $formattedData = [];

        foreach ($datas as $data) {
            $param = explode('=', $data);

            if ('orderId' === $param[0]) {
                return $param[1];
            }
        }

        return null;
    }

    public function executeTransaction(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?bool {
        $sipsClient = $this->buildClient($paymentGatewayConfiguration);

        $sipsResponse = $sipsClient->finalizeTransaction();

        if (
            '00' !== $sipsResponse->getResponseCode() ||
            '00' !== $sipsResponse->getAcquirerResponseCode() ||
            (
                self::STATUS_SUCCESS !== $sipsResponse->getHolderAuthentStatus() &&
                self::STATUS_3D_SUCCESS !== $sipsResponse->getHolderAuthentStatus()
            )
        ) {
            throw new InvalidTransactionException($sipsResponse->getHolderAuthentStatus());
        }

        return true;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'version',
            'secret',
            'merchant_id',
            'automatic_response_url',
            'normal_return_url',
            'capture_mode',
            'capture_day',
        ];
    }
}
