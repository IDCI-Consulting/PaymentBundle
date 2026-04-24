<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Worldline\Sips\Common\SipsEnvironment;
use Worldline\Sips\Paypage\PaypageRequest;
use Worldline\Sips\SipsClient;

class WorldlinePaymentGateway extends AbstractPaymentGateway
{
    protected function createSipsClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration)
    {
        return new SipsClient(
            new SipsEnvironment($paymentGatewayConfiguration->get('environment')),
            $paymentGatewayConfiguration->get('merchant_id'),
            $paymentGatewayConfiguration->get('secret_key'),
            $paymentGatewayConfiguration->get('key_version')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): array {
        $sipsClient = $this->createSipsClient($paymentGatewayConfiguration);

        $paypageRequest = new PaypageRequest();
        $paypageRequest->setAmount($transaction->getAmount());
        $paypageRequest->setCurrencyCode($transaction->getCurrencyCode());
        $paypageRequest->setAutomaticResponseUrl($paymentGatewayConfiguration->get('callback_url'));
        $paypageRequest->setNormalReturnUrl($paymentGatewayConfiguration->get('return_url'));
        $paypageRequest->setOrderChannel($paymentGatewayConfiguration->get('channel'));
        //$paypageRequest->setCustomerId($transaction->getCustomerId());
        //$paypageRequest->setOrderId($transaction->getItemId());
        $paypageRequest->setTransactionReference($transaction->getId());

        $initializationResponse = $sipsClient->initialize($paypageRequest);

        if ('00' !== $initializationResponse->getRedirectionStatusCode()) {
            throw new \UnexpectedValueException(sprintf(
                'Worldline: Invalid redirection status code %s: %s',
                $initializationResponse->getRedirectionStatusCode(),
                $initializationResponse->getRedirectionStatusMessage()
            ));
        }
        //dd($paymentGatewayConfiguration, $transaction, $options, $sipsClient, $paypageRequest, $initializationResponse);

        return [
            'initializationResponse' => $initializationResponse,
        ];
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

        return $this->templating->render('@IDCIPayment/Gateway/worldline.html.twig', [
            'initializationResponse' => $initializationData['initializationResponse'],
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
        $sipsClient = $this->createSipsClient($paymentGatewayConfiguration);
        $paypageResponse = $sipsClient->finalizeTransaction();

        dd('getCallbackResponse');
        return new GatewayResponse();
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
            ]
        );
    }
}
