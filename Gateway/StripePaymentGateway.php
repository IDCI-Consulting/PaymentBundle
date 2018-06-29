<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Stripe;
use Symfony\Component\HttpFoundation\Request;

class StripePaymentGateway extends AbstractPaymentGateway
{
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'publicKey' => $paymentGatewayConfiguration->get('public_key'),
            'transaction' => $transaction,
            'url' => $this->getReturnURL($paymentGatewayConfiguration->getAlias(), [
                'transaction_id' => $transaction->getId(),
            ]),
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/stripe.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (!$request->request->has('transactionId')) {
            return $gatewayResponse->setMessage('The request do not contains "transactionId"');
        }

        $gatewayResponse
            ->setTransactionUuid($request->get('transactionId'))
            ->setAmount($request->get('amount'))
            ->setCurrencyCode($request->get('currencyCode'))
        ;

        Stripe\Stripe::setApiKey($paymentGatewayConfiguration->get('secret_key'));

        try {
            Stripe\Charge::create([
                'amount' => $request->get('amount'),
                'currency' => $request->get('currencyCode'),
                'description' => 'Example charge',
                'source' => $request->get('stripeToken'),
            ]);
        } catch (\Exception $e) {
            return $gatewayResponse->setMessage('Unauthorized transaction');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'public_key',
            'secret_key',
        ];
    }
}
