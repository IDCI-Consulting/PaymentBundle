<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;

class AtosSipsSealPaymentGateway extends AbstractPaymentGateway
{
    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string
    {
        $options = [
            'keyVersion' => $paymentGatewayConfiguration->get('version'),
            'merchantId' => $paymentGatewayConfiguration->get('merchant_id'),
            'orderId' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'currencyCode' => $payment->getCurrencyCode(),
            'automaticResponseUrl' => $paymentGatewayConfiguration->get('automatic_response_url'),
            'normalReturnUrl' => $paymentGatewayConfiguration->get('normal_return_url'),
            'customerContact.email' => $paymentGatewayConfiguration->get('customer_contact_email'),
            'transactionReference' => sprintf('%s%s', date('mdHis'), $payment->getId()),
            'captureMode' => $paymentGatewayConfiguration->get('capture_mode'),
            'captureDay' => $paymentGatewayConfiguration->get('capture_day'),
            'paypageData.bypassReceiptPage' => $paymentGatewayConfiguration->get('bypass_receipt_page'),
        ];

        $builtOptions = [
            'options' => $options,
            'build' => implode('|', array_map(
                function ($k, $v) { return sprintf('%s=%s', $k, $v); },
                array_keys($options),
                $options
            )),
            'secret' => $paymentGatewayConfiguration->get('secret'),
        ];

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/atos_sips_seal.html.twig', [
            'url' => 'http://jarvis.inflexyon.docker/payment/create', //raw,
            'build' => $builtOptions['build'],
            'seal' => hash('sha256', mb_convert_encoding($builtOptions['build'].$builtOptions['secret'], 'UTF-8')),
        ]);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'version',
            'secret',
            'merchant_id',
            'automatic_response_url',
            'normal_return_url',
            'customer_contact_email',
            'capture_mode',
            'capture_day',
            'bypass_receipt_page',
        ];
    }
}
