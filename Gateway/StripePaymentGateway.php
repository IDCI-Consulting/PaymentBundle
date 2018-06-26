<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
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
            'url' => $this->getCallbackURL($paymentGatewayConfiguration->getAlias()),
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
            ->setStatus(Transaction::STATUS_FAILED)
        ;

        if (!$request->request->has('transaction_id')) {
            return $gatewayResponse->setMessage('The request do not contains "transaction_id"');
        }

        $gatewayResponse->setTransactionUuid($request->get('transaction_id'));

        Stripe\Stripe::setApiKey($paymentGatewayConfiguration->get('secret_key'));

        $transaction = $this->transactionManager->retrieveTransactionByUuid($gatewayResponse->getTransactionUuid());

        Stripe\Charge::create([
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrencyCode(),
            'description' => 'Example charge',
            'source' => $request->get('stripeToken'),
        ]);

        return $gatewayResponse->setStatus(Transaction::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'public_key',
            'secret_key',
        ];
    }
}
