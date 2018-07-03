<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Exception\InvalidPaymentCallbackMethodException;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;

class PayboxPaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        \Twig_Environment $templating,
        string $serverHostName,
        string $keyPath,
        string $publicKeyUrl
    ) {
        parent::__construct($templating);

        $this->serverHostName = $serverHostName;
        $this->keyPath = $keyPath;
        $this->publicKeyUrl = $publicKeyUrl;
    }

    private function getServerUrl(): string
    {
        return sprintf('https://%s/cgi/MYchoix_pagepaiement.cgi', $this->serverHostName);
    }

    private function getKeyPath($clientSite)
    {
        return sprintf('%s/%s.bin', $this->keyPath, $clientSite);
    }

    private function getPayboxReturnString()
    {
        $codeMap = array(
            'M' => 'amount',
            'R' => 'reference',
            'A' => 'authorisation_id',
            'P' => 'payment_type',
            'T' => 'call',
            'B' => 'subscription',
            'C' => 'card_type',
            'D' => 'card_validity',
            /*
            'N' => 'card_fnumber',
            'J' => 'card_lnumber',
            'O' => 'card_3dsecure',
            'F' => 'card_3dsecurestate',
            'H' => 'card_imprint',
            'Q' => 'transaction_time',
            'S' => 'transaction_number',
            'W' => 'transaction_processtime',
            'Z' => 'mixed_index',
            */
            'E' => 'error',
            'I' => 'country',
            'Y' => 'bank_country',
            'K' => 'hash',
        );

        return implode(';', array_map(
            function ($k, $v) { return sprintf('%s:%s', $v, $k); },
            array_keys($codeMap),
            $codeMap
        ));
    }

    private function buildOptions(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'PBX_SITE' => $paymentGatewayConfiguration->get('client_site'),
            'PBX_RANG' => $paymentGatewayConfiguration->get('client_rang'),
            'PBX_IDENTIFIANT' => $paymentGatewayConfiguration->get('client_id'),
            'PBX_TOTAL' => $transaction->getAmount(),
            'PBX_DEVISE' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'PBX_CMD' => $transaction->getId(),
            'PBX_PORTEUR' => 'me@mail.com',
            'PBX_EFFECTUE' => $paymentGatewayConfiguration->get('return_url'),
            'PBX_REFUSE' => $paymentGatewayConfiguration->get('return_url'),
            'PBX_ANNULE' => $paymentGatewayConfiguration->get('return_url'),
            'PBX_HASH' => 'sha512',
            'PBX_RUF1' => 'POST',
            'PBX_REPONDRE_A' => $paymentGatewayConfiguration->get('callback_url'),
            'PBX_RETOUR' => $this->getPayboxReturnString(),
            'PBX_TIME' => date('c'),
            'PBX_TYPEPAIEMENT' => 'CARTE',
            'PBX_TYPECARTE' => 'CB',
        ];
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $options = $this->buildOptions($paymentGatewayConfiguration, $transaction);

        $builtOptions = [
            'options' => $options,
            'build' => implode('&', array_map(
                function ($k, $v) { return sprintf('%s=%s', $k, $v); },
                array_keys($options),
                $options
            )),
        ];

        $binKey = file_get_contents($this->getKeyPath($options['PBX_SITE']));

        $builtOptions['options']['PBX_HMAC'] = strtoupper(
            hash_hmac($builtOptions['options']['PBX_HASH'], $builtOptions['build'], $binKey)
        );

        return [
            'url' => $this->getServerUrl(),
            'options' => $builtOptions['options'],
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paybox.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('GET')) {
            throw new InvalidPaymentCallbackMethodException('Request method should be GET');
        }

        if (!$request->query->has('reference')) {
            return $gatewayResponse->setMessage('The request not contains "reference"');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
            ->setAmount($request->get('amount'))
            ->setTransactionUuid($request->get('reference'))
            ->setRaw($request->query->all())
        ;

        if ('00000' !== $request->get('error')) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        $publicKey = openssl_pkey_get_public(
            (new Client())->request('GET', $this->publicKeyUrl)->getBody()
        );

        $data = $request->query->all();
        unset($data['hash']);

        $builtQuery = implode('&', array_map(
            function ($k, $v) {
                return sprintf('%s=%s', $k, urlencode($v));
            },
            array_keys($data),
            $data
        ));

        if (!openssl_verify($builtQuery, base64_decode($request->get('hash')), $publicKey, 'sha1WithRSAEncryption')) {
            return $gatewayResponse->setMessage('Could not verify the integrity of paybox return response');
        }

        openssl_free_key($publicKey);

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'client_id',
                'client_rang',
                'client_site',
            ]
        );
    }
}
