<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use GuzzleHttp\Client;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Payum\ISO4217\ISO4217;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayboxPaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var string
     */
    private $serverHostName;

    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        string $serverHostName,
        string $keyPath,
        string $publicKeyUrl
    ) {
        parent::__construct($templating, $router);

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

    private function buildOptions(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): array
    {
        $callbackUrl = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());

        return [
            'PBX_SITE' => $paymentGatewayConfiguration->get('client_site'),
            'PBX_RANG' => $paymentGatewayConfiguration->get('client_rang'),
            'PBX_IDENTIFIANT' => $paymentGatewayConfiguration->get('client_id'),
            'PBX_TOTAL' => $transaction->getAmount(),
            'PBX_DEVISE' => (new ISO4217())->findByAlpha3($transaction->getCurrencyCode())->getNumeric(),
            'PBX_CMD' => $transaction->getId(),
            'PBX_PORTEUR' => 'me@mail.com',
            'PBX_EFFECTUE' => $callbackUrl,
            'PBX_REFUSE' => $callbackUrl,
            'PBX_ANNULE' => $callbackUrl,
            'PBX_HASH' => 'sha512',
            'PBX_RUF1' => 'POST',
            'PBX_RETOUR' => $this->getPayboxReturnString(),
            'PBX_TIME' => date('c'),
            'PBX_TYPEPAIEMENT' => 'CARTE',
            'PBX_TYPECARTE' => 'CB',
        ];
    }

    private function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ) {
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

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paybox.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): string
    {
        if (!$request->request->has('reference') && !$request->query->has('reference')) {
            throw new \Exception("The request not contains 'reference'");
        }

        return $request->get('reference');
    }

    public function callback(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?Transaction {
        if ('00000' !== $request->get('error')) {
            throw new \Exception('Transaction unauthorized');
        }

        if ($transaction->getAmount() != $request->get('amount')) {
            throw new \Exception('Amount');
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
            throw new \Exception('SSL Key');
        }

        openssl_free_key($publicKey);

        $transaction->setStatus(Transaction::STATUS_VALIDATED);

        return $transaction;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
            'client_rang',
            'client_site',
        ];
    }
}
